<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Constantes;
use App\Exceptions\ConflictoException;
use App\Exceptions\ValidacionException;

/**
 * RN-06 / RN-05 / RN-09 contra la base de datos real (pruebas de integración).
 * Comprueba que la máquina de estados y el tope se aplican de verdad dentro de
 * las transacciones, y que cada operación deja su historial.
 */
final class FlujoTicketTest extends IntegrationTestCase
{
    private const DESCRIPCION = 'Prueba de integración PHPUnit: el monitor no enciende.';

    public function test_flujo_completo_abierto_a_cerrado_con_historial(): void
    {
        // 1) Crear (ABIERTO) con un técnico con cupo.
        $creado = $this->tickets->crear([
            'usuario_codigo' => 'U001',
            'categoria_id' => 1,
            'descripcion' => self::DESCRIPCION,
            'prioridad' => 'Alta',
            'tecnico_id' => $this->tecnicoConCupo(),
        ]);
        $this->recordarNumero($creado['numero']);

        self::assertSame('ABIERTO', $creado['estado']);
        self::assertSame('EN_TIEMPO', $creado['sla']);
        self::assertSame('CREACION', $creado['historial'][array_key_last($creado['historial'])]['tipo_cambio']);

        // 2) ABIERTO -> EN CURSO.
        $enCurso = $this->tickets->cambiarEstado($creado['numero'], ['estado' => 'EN CURSO']);
        self::assertSame('EN CURSO', $enCurso['estado']);

        // 3) EN CURSO -> CERRADO exige solución y deja SLA null.
        $cerrado = $this->tickets->cambiarEstado($creado['numero'], [
            'estado' => 'CERRADO',
            'observacion' => 'Se reemplazó la fuente de poder y quedó operativo.',
        ]);
        self::assertSame('CERRADO', $cerrado['estado']);
        self::assertNull($cerrado['sla']);
        self::assertSame('Se reemplazó la fuente de poder y quedó operativo.', $cerrado['solucion']);
        self::assertNotNull($cerrado['cerrado']);

        // RN-09: quedaron 3 eventos en el historial (creación + 2 estados).
        self::assertCount(3, $cerrado['historial']);
    }

    public function test_no_permite_saltar_de_abierto_a_cerrado(): void
    {
        $creado = $this->tickets->crear([
            'usuario_codigo' => 'U002',
            'categoria_id' => 2,
            'descripcion' => self::DESCRIPCION,
            'prioridad' => 'Media',
            'tecnico_id' => $this->tecnicoConCupo(),
        ]);
        $this->recordarNumero($creado['numero']);

        try {
            $this->tickets->cambiarEstado($creado['numero'], [
                'estado' => 'CERRADO',
                'observacion' => 'Intento de salto.',
            ]);
            self::fail('Debió lanzar ConflictoException por transición inválida.');
        } catch (ConflictoException $e) {
            self::assertSame(409, $e->estado());
            self::assertSame('TRANSICION_INVALIDA', $e->codigo());
        }

        // El ticket sigue intacto en ABIERTO (la transacción hizo ROLLBACK).
        self::assertSame('ABIERTO', $this->filaTicket($creado['numero'])['estado']);
    }

    public function test_cerrar_sin_solucion_es_validacion_422(): void
    {
        $creado = $this->tickets->crear([
            'usuario_codigo' => 'U003',
            'categoria_id' => 3,
            'descripcion' => self::DESCRIPCION,
            'prioridad' => 'Baja',
            'tecnico_id' => $this->tecnicoConCupo(),
        ]);
        $this->recordarNumero($creado['numero']);
        $this->tickets->cambiarEstado($creado['numero'], ['estado' => 'EN CURSO']);

        $this->expectException(ValidacionException::class);
        $this->tickets->cambiarEstado($creado['numero'], ['estado' => 'CERRADO']);
    }

    public function test_usuario_inactivo_no_puede_crear_ticket(): void
    {
        $this->expectException(ValidacionException::class);
        $this->tickets->crear([
            'usuario_codigo' => 'U010', // inactivo en el seed
            'categoria_id' => 1,
            'descripcion' => self::DESCRIPCION,
            'prioridad' => 'Media',
            'tecnico_id' => $this->tecnicoConCupo(),
        ]);
    }

    /**
     * RN-05: al llenar a un técnico hasta el tope, el siguiente intento recibe
     * 409 TECNICO_AL_TOPE y NO se quema el consecutivo (ROLLBACK).
     */
    public function test_tecnico_al_tope_rechaza_y_no_quema_consecutivo(): void
    {
        $tecnicoId = $this->tecnicoConCupo();
        $creados = [];

        // Rellenar el cupo disponible del técnico.
        for ($i = 0; $i < Constantes::MAX_TICKETS_TECNICO; $i++) {
            try {
                $t = $this->tickets->crear([
                    'usuario_codigo' => 'U00' . (($i % 5) + 1),
                    'categoria_id' => 1,
                    'descripcion' => self::DESCRIPCION . " #{$i}",
                    'prioridad' => 'Baja',
                    'tecnico_id' => $tecnicoId,
                ]);
                $creados[] = $t['numero'];
                $this->recordarNumero($t['numero']);
            } catch (ConflictoException) {
                break; // ya estaba en el tope
            }
        }

        $consecutivoAntes = $this->consecutivoActual();

        try {
            $this->tickets->crear([
                'usuario_codigo' => 'U001',
                'categoria_id' => 1,
                'descripcion' => self::DESCRIPCION,
                'prioridad' => 'Media',
                'tecnico_id' => $tecnicoId,
            ]);
            self::fail('Debió lanzar TECNICO_AL_TOPE.');
        } catch (ConflictoException $e) {
            self::assertSame('TECNICO_AL_TOPE', $e->codigo());
        }

        self::assertSame(
            $consecutivoAntes,
            $this->consecutivoActual(),
            'El consecutivo no debe quemarse cuando falla el tope (ROLLBACK).',
        );
    }

    /** Técnico activo con carga actual < tope; si ninguno tiene cupo, se omite. */
    private function tecnicoConCupo(): int
    {
        $stmt = $this->pdo->query(
            "SELECT te.id, COUNT(t.id) AS carga
             FROM tecnicos te
             LEFT JOIN tickets t ON t.tecnico_id = te.id
                 AND t.estado IN ('ABIERTO', 'EN CURSO')
             WHERE te.activo = 1
             GROUP BY te.id
             HAVING carga < " . Constantes::MAX_TICKETS_TECNICO . '
             ORDER BY carga ASC
             LIMIT 1',
        );
        $fila = $stmt->fetch();
        if ($fila === false) {
            self::markTestSkipped('No hay técnicos activos con cupo disponible.');
        }

        return (int) $fila['id'];
    }

    private function consecutivoActual(): int
    {
        $stmt = $this->pdo->prepare('SELECT ultimo FROM consecutivos_ticket WHERE anio = :anio');
        $stmt->execute([':anio' => (int) date('Y')]);
        $fila = $stmt->fetch();

        return $fila === false ? 0 : (int) $fila['ultimo'];
    }
}
