'use strict';

/**
 * Tabla de tickets con filtros y paginación EN SERVIDOR (AGENTS.md §7):
 * columnas exactas (número, usuario, categoría, prioridad, técnico, estado,
 * creación, SLA) y 10 filas por página. Cada fila abre el detalle.
 */
window.Tabla = (function () {
  var estado = {
    pagina: 1,
    porPagina: 10,
    total: 0,
    totalPaginas: 1,
    filtros: { estado: '', prioridad: '', tecnico: '', q: '' },
  };

  function h(etiqueta, atributos, hijos) {
    return Utils.h(etiqueta, atributos, hijos);
  }

  function iniciar() {
    Utils.porId('filtro-estado').addEventListener('change', aplicarFiltros);
    Utils.porId('filtro-prioridad').addEventListener('change', aplicarFiltros);
    Utils.porId('filtro-tecnico').addEventListener('change', aplicarFiltros);
    Utils.porId('filtro-q').addEventListener('input', Utils.debounce(aplicarFiltros, 400));
    Utils.porId('btn-limpiar').addEventListener('click', limpiarFiltros);
    Utils.porId('btn-exportar').addEventListener('click', exportarCsv);
    Utils.porId('btn-prev').addEventListener('click', function () {
      if (estado.pagina > 1) {
        estado.pagina -= 1;
        cargar();
      }
    });
    Utils.porId('btn-next').addEventListener('click', function () {
      if (estado.pagina < estado.totalPaginas) {
        estado.pagina += 1;
        cargar();
      }
    });

    cargarTecnicosDelFiltro();
  }

  /** Carga el filtro de técnicos desde la API (nunca quemado en el HTML, §7). */
  function cargarTecnicosDelFiltro() {
    Ui.obtenerTecnicos().then(function (tecnicos) {
      var select = Utils.porId('filtro-tecnico');
      var valorActual = select.value;
      Utils.limpiar(select);
      select.append(h('option', { value: '', texto: 'Todos' }));
      tecnicos.forEach(function (tecnico) {
        select.append(h('option', { value: tecnico.id, texto: tecnico.nombre }));
      });
      // Conserva la selección del usuario si el técnico sigue existiendo.
      select.value = valorActual;
    }).catch(function () {
      /* el filtro es secundario: si falla, la tabla sigue funcionando */
    });
  }

  function aplicarFiltros() {
    estado.filtros = {
      estado: Utils.porId('filtro-estado').value,
      prioridad: Utils.porId('filtro-prioridad').value,
      tecnico: Utils.porId('filtro-tecnico').value,
      q: Utils.porId('filtro-q').value.trim(),
    };
    estado.pagina = 1;
    cargar();
  }

  function limpiarFiltros() {
    Utils.porId('filtro-estado').value = '';
    Utils.porId('filtro-prioridad').value = '';
    Utils.porId('filtro-tecnico').value = '';
    Utils.porId('filtro-q').value = '';
    aplicarFiltros();
  }

  function cargar() {
    var parametros = {
      estado: estado.filtros.estado,
      prioridad: estado.filtros.prioridad,
      tecnico: estado.filtros.tecnico,
      q: estado.filtros.q,
      page: estado.pagina,
      per_page: estado.porPagina,
    };
    return Api.listarTickets(parametros).then(function (respuesta) {
      estado.total = respuesta.meta.total;
      estado.pagina = respuesta.meta.page;
      estado.porPagina = respuesta.meta.per_page;
      estado.totalPaginas = Math.max(1, Math.ceil(estado.total / estado.porPagina));
      render(respuesta.data);
      actualizarPaginacion();
      // Se recarga en cada refresh para reflejar altas/cierres de tickets: así el
      // filtro no queda con una lista desactualizada tras cambiar un estado.
      cargarTecnicosDelFiltro();
    }).catch(function (error) {
      var cuerpo = Utils.porId('tabla-cuerpo');
      Utils.limpiar(cuerpo);
      cuerpo.append(h('tr', null, [h('td', { colspan: '8', clase: 'vacio', texto: error.message })]));
      Ui.avisarError(error);
    });
  }

  function render(filas) {
    var cuerpo = Utils.porId('tabla-cuerpo');
    Utils.limpiar(cuerpo);

    if (!filas.length) {
      cuerpo.append(h('tr', null, [h('td', { colspan: '8', clase: 'vacio', texto: 'No hay tickets con estos filtros.' })]));
      return;
    }

    filas.forEach(function (ticket) {
      var fila = h('tr', {
        onclick: function () {
          Ui.abrirDetalle(ticket.numero);
        },
      }, [
        h('td', { texto: ticket.numero }),
        h('td', { texto: ticket.usuario }),
        h('td', { texto: ticket.categoria }),
        h('td', null, [Ui.insigniaPrioridad(ticket.prioridad)]),
        h('td', { texto: ticket.tecnico }),
        h('td', null, [Ui.insigniaEstado(ticket.estado)]),
        h('td', { texto: Utils.formatearFecha(ticket.creado) }),
        h('td', null, [Ui.insigniaSla(ticket.sla)]),
      ]);
      cuerpo.append(fila);
    });
  }

  /**
   * Descarga un CSV con los tickets que cumplen los filtros activos (no la
   * página actual). El blob se genera con el encabezado X-API-Key que envía
   * Api.exportarCsv; el enlace temporal solo materializa la descarga.
   */
  function exportarCsv() {
    Api.exportarCsv({
      estado: estado.filtros.estado,
      prioridad: estado.filtros.prioridad,
      tecnico: estado.filtros.tecnico,
      q: estado.filtros.q,
    }).then(function (resultado) {
      var url = URL.createObjectURL(resultado.blob);
      var enlace = h('a', { href: url, download: resultado.nombre });
      document.body.append(enlace);
      enlace.click();
      enlace.remove();
      URL.revokeObjectURL(url);
      Ui.avisar('Exportación CSV generada.', 'ok');
    }).catch(Ui.avisarError);
  }

  function actualizarPaginacion() {
    Utils.porId('pagina-info').textContent =
      'Página ' + estado.pagina + ' de ' + estado.totalPaginas + ' · ' + estado.total + ' tickets';
    Utils.porId('btn-prev').disabled = estado.pagina <= 1;
    Utils.porId('btn-next').disabled = estado.pagina >= estado.totalPaginas;
  }

  return {
    iniciar: iniciar,
    cargar: cargar,
  };
})();
