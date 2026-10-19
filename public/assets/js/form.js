'use strict';

/**
 * Formulario de creación de ticket (AGENTS.md §7): búsqueda de usuario con
 * debounce (~400 ms), catálogos cargados desde la API (categorías y técnicos,
 * con el de menor carga preseleccionado y los del tope deshabilitados) y
 * mapeo de los `fields` del error 422 a cada input.
 */
window.Formulario = (function () {
  var DESCRIPCION_MIN = 20;
  var DESCRIPCION_MAX = 1000;
  var usuarioValido = false;

  var CAMPOS = {
    usuario_codigo: 'nuevo-usuario',
    categoria_id: 'nuevo-categoria',
    prioridad: 'nuevo-prioridad',
    tecnico_id: 'nuevo-tecnico',
    descripcion: 'nuevo-descripcion',
  };

  function h(etiqueta, atributos, hijos) {
    return Utils.h(etiqueta, atributos, hijos);
  }

  function iniciar() {
    Utils.porId('btn-nuevo').addEventListener('click', abrir);
    Utils.porId('form-nuevo').addEventListener('submit', function (evento) {
      evento.preventDefault();
      crear();
    });
    Utils.porId('nuevo-usuario').addEventListener('input', Utils.debounce(buscarUsuario, 400));
    Utils.porId('nuevo-descripcion').addEventListener('input', function () {
      actualizarContador();
      validarBoton();
    });
    Utils.porId('nuevo-categoria').addEventListener('change', validarBoton);
    Utils.porId('nuevo-tecnico').addEventListener('change', validarBoton);
  }

  function abrir() {
    Utils.porId('form-nuevo').reset();
    Utils.porId('form-error').textContent = '';
    Utils.porId('nuevo-usuario-info').textContent = '';
    usuarioValido = false;
    limpiarErroresCampos();
    actualizarContador();
    cargarCatalogos();
    Utils.porId('modal-nuevo').showModal();
  }

  function actualizarContador() {
    var longitud = Utils.porId('nuevo-descripcion').value.trim().length;
    Utils.porId('nuevo-descripcion-contador').textContent = longitud + ' / ' + DESCRIPCION_MAX + ' caracteres';
  }

  function cargarCatalogos() {
    Ui.obtenerCategorias().then(function (categorias) {
      var select = Utils.porId('nuevo-categoria');
      Utils.limpiar(select);
      categorias.forEach(function (categoria) {
        select.append(h('option', { value: categoria.id, texto: categoria.nombre }));
      });
      validarBoton();
    }).catch(Ui.avisarError);

    Ui.obtenerTecnicos().then(function (tecnicos) {
      var select = Utils.porId('nuevo-tecnico');
      Utils.limpiar(select);
      var preseleccionado = null;
      tecnicos.forEach(function (tecnico) {
        select.append(h('option', {
          value: tecnico.id,
          texto: tecnico.nombre + ' (' + tecnico.carga_activa + '/5)'
            + (tecnico.disponible ? '' : ' — al tope'),
          disabled: tecnico.disponible ? null : 'disabled',
        }));
        if (tecnico.disponible && preseleccionado === null) {
          preseleccionado = tecnico.id; // vienen ordenados de menor a mayor carga
        }
      });
      if (preseleccionado !== null) {
        select.value = String(preseleccionado);
      }
      validarBoton();
    }).catch(Ui.avisarError);
  }

  function buscarUsuario() {
    var codigo = Utils.porId('nuevo-usuario').value.trim();
    var info = Utils.porId('nuevo-usuario-info');
    if (!codigo) {
      info.textContent = '';
      usuarioValido = false;
      validarBoton();
      return;
    }
    info.textContent = 'Buscando…';
    Api.usuarios(codigo).then(function (usuario) {
      info.textContent = usuario.nombre + ' · ' + usuario.cargo + ' · ' + usuario.area;
      usuarioValido = true;
      validarBoton();
    }).catch(function (error) {
      info.textContent = error.http === 404 ? 'Usuario no encontrado o inactivo.' : error.message;
      usuarioValido = false;
      validarBoton();
    });
  }

  /** Habilita "Registrar" solo con datos completos y válidos. */
  function validarBoton() {
    var descripcion = Utils.porId('nuevo-descripcion').value.trim();
    var tieneDescripcion = descripcion.length >= DESCRIPCION_MIN && descripcion.length <= DESCRIPCION_MAX;
    var tecnico = Utils.porId('nuevo-tecnico');
    var tecnicoOk = tecnico.value !== '' && tecnico.selectedOptions.length > 0 && !tecnico.selectedOptions[0].disabled;
    var categoriaOk = Utils.porId('nuevo-categoria').value !== '';
    Utils.porId('btn-registrar').disabled = !(usuarioValido && tieneDescripcion && tecnicoOk && categoriaOk);
  }

  function crear() {
    var error = Utils.porId('form-error');
    error.textContent = '';
    limpiarErroresCampos();

    var datos = {
      usuario_codigo: Utils.porId('nuevo-usuario').value.trim(),
      categoria_id: Number(Utils.porId('nuevo-categoria').value),
      prioridad: Utils.porId('nuevo-prioridad').value,
      tecnico_id: Number(Utils.porId('nuevo-tecnico').value),
      descripcion: Utils.porId('nuevo-descripcion').value.trim(),
    };

    Api.crearTicket(datos).then(function (ticket) {
      Utils.porId('modal-nuevo').close();
      Ui.avisar('Ticket ' + ticket.numero + ' registrado.', 'ok');
      Ui.invalidarCatalogos();
      Ui.refrescarLista();
    }).catch(function (err) {
      error.textContent = err.message;
      mapearCampos(err.fields);
    });
  }

  /** Resalta cada input inválido con el mensaje devuelto por la API (422 fields). */
  function mapearCampos(fields) {
    if (!fields) {
      return;
    }
    var mensajes = [];
    Object.keys(fields).forEach(function (campo) {
      var id = CAMPOS[campo];
      var input = id ? Utils.porId(id) : null;
      if (input) {
        var envoltura = input.closest('.campo');
        if (envoltura) {
          envoltura.classList.add('campo--error');
        }
        input.addEventListener('input', function quitar() {
          if (envoltura) {
            envoltura.classList.remove('campo--error');
          }
          input.removeEventListener('input', quitar);
        });
      }
      mensajes.push(fields[campo]);
    });
    if (mensajes.length) {
      Utils.porId('form-error').textContent = mensajes.join(' ');
    }
  }

  function limpiarErroresCampos() {
    Object.keys(CAMPOS).forEach(function (campo) {
      var input = Utils.porId(CAMPOS[campo]);
      if (input && input.closest('.campo')) {
        input.closest('.campo').classList.remove('campo--error');
      }
    });
  }

  return {
    iniciar: iniciar,
  };
})();
