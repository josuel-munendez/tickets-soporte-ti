'use strict';

/**
 * Capa de presentación (AGENTS.md §7): construye el tablero, el modal de detalle
 * y los avisos. Todo el DOM se arma con Utils.h (createElement/textContent), así
 * que los datos de la API nunca se inyectan como HTML (§4.5).
 */
window.Ui = (function () {
  var SIGUIENTE = { 'ABIERTO': 'EN CURSO', 'EN CURSO': 'CERRADO', 'CERRADO': null };
  var CLASE_ESTADO = { 'ABIERTO': 'abierto', 'EN CURSO': 'curso', 'CERRADO': 'cerrado' };
  var CLASE_PRIORIDAD = { 'Alta': 'alta', 'Media': 'media', 'Baja': 'baja' };
  var CLASE_SLA = { 'EN_TIEMPO': 'en_tiempo', 'POR_VENCER': 'por_vencer', 'VENCIDO': 'vencido' };

  var ticketActual = null;
  var cacheTecnicos = null;
  var cacheCategorias = null;

  function h(etiqueta, atributos, hijos) {
    return Utils.h(etiqueta, atributos, hijos);
  }

  function insignia(texto, clase) {
    return h('span', { clase: 'insignia insignia--' + clase, texto: texto });
  }

  function insigniaEstado(estado) {
    return insignia(estado, CLASE_ESTADO[estado] || 'nulo');
  }

  function insigniaPrioridad(prioridad) {
    return insignia(prioridad, CLASE_PRIORIDAD[prioridad] || 'nulo');
  }

  function insigniaSla(sla) {
    return insignia(sla || '—', sla ? (CLASE_SLA[sla] || 'nulo') : 'nulo');
  }

  function iniciar() {
    document.querySelectorAll('[data-cerrar]').forEach(function (boton) {
      boton.addEventListener('click', function () {
        var dialogo = boton.closest('dialog');
        if (dialogo) {
          dialogo.close();
        }
      });
    });

    Utils.porId('btn-cambiar-estado').addEventListener('click', aplicarEstado);
    Utils.porId('btn-reasignar').addEventListener('click', aplicarReasignacion);
  }

  /** Aviso flotante. tipo: 'ok' | 'error'. */
  function avisar(mensaje, tipo) {
    var aviso = Utils.porId('aviso');
    aviso.textContent = mensaje;
    aviso.className = 'aviso aviso--visible' + (tipo ? ' aviso--' + tipo : '');
    clearTimeout(avisar._t);
    avisar._t = setTimeout(function () {
      aviso.className = 'aviso';
    }, 3500);
  }

  function avisarError(error) {
    avisar(error && error.message ? error.message : 'Ocurrió un error inesperado.', 'error');
  }

  /** Obtiene (y cachea) el catálogo de técnicos con su carga. */
  function obtenerTecnicos() {
    if (cacheTecnicos) {
      return Promise.resolve(cacheTecnicos);
    }
    return Api.tecnicos().then(function (respuesta) {
      cacheTecnicos = respuesta.data || [];
      return cacheTecnicos;
    });
  }

  /** Obtiene (y cachea) el catálogo de categorías. */
  function obtenerCategorias() {
    if (cacheCategorias) {
      return Promise.resolve(cacheCategorias);
    }
    return Api.categorias().then(function (respuesta) {
      cacheCategorias = respuesta.data || [];
      return cacheCategorias;
    });
  }

  function invalidarCatalogos() {
    cacheTecnicos = null;
  }

  // --- Tablero ------------------------------------------------------------

  function cargarResumen() {
    return Api.resumen().then(renderResumen).catch(avisarError);
  }

  function renderResumen(resumen) {
    var tablero = Utils.porId('tablero');
    Utils.limpiar(tablero);

    var estados = resumen.por_estado || {};
    tablero.append(tarjeta('abierto', estados['ABIERTO'] || 0, 'Abiertos'));
    tablero.append(tarjeta('curso', estados['EN CURSO'] || 0, 'En curso'));
    tablero.append(tarjeta('cerrado', estados['CERRADO'] || 0, 'Cerrados'));
    tablero.append(tarjeta('vencido', resumen.vencidos || 0, 'SLA vencido'));

    var carga = resumen.carga_por_tecnico || [];
    var lista = h('div', { clase: 'tarjeta__carga' }, carga.map(function (tecnico) {
      return h('div', {
        texto: tecnico.nombre + ' — ' + tecnico.carga_activa + '/' + 5
          + (tecnico.disponible ? '' : ' (al tope)'),
      });
    }));
    tablero.append(h('div', { clase: 'tarjeta' }, [
      h('span', { clase: 'tarjeta__etiqueta', texto: 'Carga por técnico' }),
      lista,
    ]));
  }

  function tarjeta(variante, valor, etiqueta) {
    return h('div', { clase: 'tarjeta tarjeta--' + variante }, [
      h('span', { clase: 'tarjeta__valor', texto: valor }),
      h('span', { clase: 'tarjeta__etiqueta', texto: etiqueta }),
    ]);
  }

  // --- Detalle ------------------------------------------------------------

  function abrirDetalle(numero) {
    var dialogo = Utils.porId('modal-detalle');
    Utils.porId('detalle-titulo').textContent = numero;
    var cuerpo = Utils.porId('detalle-cuerpo');
    Utils.limpiar(cuerpo);
    cuerpo.append(h('p', { texto: 'Cargando…' }));
    dialogo.showModal();

    Api.verTicket(numero)
      .then(renderDetalle)
      .catch(function (error) {
        Utils.limpiar(cuerpo);
        cuerpo.append(h('p', { clase: 'error', texto: error.message }));
      });
  }

  function renderDetalle(ticket) {
    ticketActual = ticket;

    Utils.porId('detalle-titulo').textContent = ticket.numero + ' · ' + ticket.usuario;
    var cuerpo = Utils.porId('detalle-cuerpo');
    Utils.limpiar(cuerpo);

    cuerpo.append(h('div', { clase: 'detalle-cabecera' }, [
      h('h3', { texto: ticket.numero }),
      insigniaEstado(ticket.estado),
      insigniaPrioridad(ticket.prioridad),
      insigniaSla(ticket.sla),
    ]));

    var grilla = h('div', { clase: 'detalle-grid' }, [
      dato('Usuario', ticket.usuario + ' (' + ticket.usuario_codigo + ')'),
      dato('Categoría', ticket.categoria),
      dato('Técnico', ticket.tecnico),
      dato('Prioridad', ticket.prioridad),
      dato('Estado', ticket.estado),
      dato('Creado', Utils.formatearFecha(ticket.creado)),
      dato('Vence (SLA)', Utils.formatearFecha(ticket.vence)),
      dato('Cerrado', Utils.formatearFecha(ticket.cerrado)),
    ]);
    cuerpo.append(grilla);

    cuerpo.append(bloque('Descripción', ticket.descripcion));
    if (ticket.solucion) {
      cuerpo.append(bloque('Solución', ticket.solucion));
    }

    cuerpo.append(h('div', { clase: 'bloque' }, [
      h('h4', { texto: 'Historial' }),
      h('ul', { clase: 'historial' }, (ticket.historial || []).map(itemHistorial)),
    ]));

    prepararAcciones(ticket);
  }

  function dato(etiqueta, valor) {
    return h('div', null, [
      h('dt', { texto: etiqueta }),
      h('dd', { texto: valor }),
    ]);
  }

  function bloque(titulo, contenido) {
    return h('div', { clase: 'bloque' }, [
      h('h4', { texto: titulo }),
      h('p', { texto: contenido }),
    ]);
  }

  function itemHistorial(evento) {
    return h('li', null, [
      h('time', { texto: Utils.formatearFecha(evento.fecha) }),
      h('div', { texto: describirEvento(evento) }),
    ]);
  }

  function describirEvento(evento) {
    if (evento.tipo_cambio === 'CREACION') {
      return 'Ticket registrado en estado ' + evento.estado_nuevo;
    }
    if (evento.tipo_cambio === 'REASIGNACION') {
      return 'Reasignado: ' + (evento.tecnico_anterior || '—') + ' → ' + (evento.tecnico_nuevo || '—')
        + (evento.observacion ? ' · ' + evento.observacion : '');
    }
    return 'Estado: ' + (evento.estado_anterior || '—') + ' → ' + evento.estado_nuevo
      + (evento.observacion ? ' · ' + evento.observacion : '');
  }

  /** Configura el select de estado y el de técnicos del modal de detalle. */
  function prepararAcciones(ticket) {
    var siguiente = SIGUIENTE[ticket.estado];
    var selectEstado = Utils.porId('detalle-estado');
    Utils.limpiar(selectEstado);
    if (siguiente) {
      selectEstado.append(h('option', { value: siguiente, texto: siguiente }));
      selectEstado.disabled = false;
    } else {
      selectEstado.append(h('option', { value: '', texto: 'Sin transición (cerrado)' }));
      selectEstado.disabled = true;
    }
    Utils.porId('btn-cambiar-estado').disabled = !siguiente;
    Utils.porId('detalle-observacion').value = '';

    var selectTecnico = Utils.porId('detalle-tecnico');
    Utils.limpiar(selectTecnico);
    selectTecnico.append(h('option', { value: '', texto: 'Seleccione técnico…' }));
    obtenerTecnicos().then(function (tecnicos) {
      tecnicos.forEach(function (tecnico) {
        var esActual = String(tecnico.id) === String(ticket.tecnico_id);
        selectTecnico.append(h('option', {
          value: tecnico.id,
          texto: tecnico.nombre + ' (' + tecnico.carga_activa + '/5)'
            + (tecnico.disponible ? '' : ' — al tope'),
          disabled: (!tecnico.disponible && !esActual) ? 'disabled' : null,
          selected: esActual ? 'selected' : null,
        }));
      });
    }).catch(avisarError);
    Utils.porId('btn-reasignar').disabled = ticket.estado === 'CERRADO';
  }

  function aplicarEstado() {
    if (!ticketActual) {
      return;
    }
    var estado = Utils.porId('detalle-estado').value;
    var observacion = Utils.porId('detalle-observacion').value.trim();
    if (!estado) {
      avisar('Este ticket no admite más cambios de estado.', 'error');
      return;
    }
    if (estado === 'CERRADO' && !observacion) {
      avisar('Para cerrar el ticket indique la solución aplicada.', 'error');
      return;
    }
    Api.cambiarEstado(ticketActual.numero, { estado: estado, observacion: observacion })
      .then(function (ticket) {
        avisar('Estado actualizado a ' + ticket.estado + '.', 'ok');
        renderDetalle(ticket);
        refrescarLista();
      })
      .catch(avisarError);
  }

  function aplicarReasignacion() {
    if (!ticketActual) {
      return;
    }
    var tecnico = Utils.porId('detalle-tecnico').value;
    if (!tecnico) {
      avisar('Seleccione un técnico para reasignar.', 'error');
      return;
    }
    Api.reasignarTecnico(ticketActual.numero, { tecnico_id: Number(tecnico) })
      .then(function (ticket) {
        avisar('Ticket reasignado a ' + ticket.tecnico + '.', 'ok');
        invalidarCatalogos();
        renderDetalle(ticket);
        refrescarLista();
      })
      .catch(avisarError);
  }

  /** Recarga la tabla y el tablero (lo llama Ui tras un cambio). */
  function refrescarLista() {
    if (window.Tabla) {
      window.Tabla.cargar();
    }
    cargarResumen();
  }

  return {
    iniciar: iniciar,
    avisar: avisar,
    avisarError: avisarError,
    cargarResumen: cargarResumen,
    abrirDetalle: abrirDetalle,
    obtenerTecnicos: obtenerTecnicos,
    obtenerCategorias: obtenerCategorias,
    invalidarCatalogos: invalidarCatalogos,
    insigniaEstado: insigniaEstado,
    insigniaPrioridad: insigniaPrioridad,
    insigniaSla: insigniaSla,
    refrescarLista: refrescarLista,
  };
})();
