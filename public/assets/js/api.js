'use strict';

/**
 * Cliente HTTP de la API REST (AGENTS.md §7: "api.js — único punto de fetch").
 * No hay ningún otro fetch en la SPA: todo pasa por aquí, siempre con el
 * encabezado X-API-Key (§4.3) y traduciendo el formato único de error (§5)
 * a un Error con `codigo`, `fields` y `http`.
 */
window.Api = (function () {
  var BASE = '/api';
  var CLAVE_ALMACENADA = 'soporte_ti_api_key';
  var MARCADOR = '__CLAVE_API__';

  function claveInyectada() {
    var config = window.APP_CONFIG || {};
    if (config.apiKey && config.apiKey !== MARCADOR) {
      return String(config.apiKey);
    }
    return '';
  }

  var clave = claveInyectada() || leerClaveGuardada() || '';

  function leerClaveGuardada() {
    try {
      return localStorage.getItem(CLAVE_ALMACENADA) || '';
    } catch (e) {
      return '';
    }
  }

  function guardarClave(nueva) {
    clave = nueva;
    try {
      localStorage.setItem(CLAVE_ALMACENADA, nueva);
    } catch (e) {
      /* almacenamiento no disponible: la clave vive solo en memoria */
    }
  }

  function obtenerClave() {
    return clave;
  }

  /** Pide la clave por pantalla (solo cuando app.html se abrió como archivo estático). */
  function solicitarClave() {
    var nueva = window.prompt('API key (encabezado X-API-Key):', clave || '');
    if (nueva) {
      guardarClave(nueva.trim());
    }
    return clave;
  }

  function asegurarClave() {
    if (!clave) {
      solicitarClave();
    }
    if (!clave) {
      var error = new Error('Falta la API key. Recarga e ingrésala para consultar.');
      error.codigo = 'SIN_CLAVE';
      error.fields = {};
      throw error;
    }
  }

  function extraerError(respuesta, datos) {
    var info = (datos && datos.error) ? datos.error : {};
    var error = new Error(info.message || 'Ocurrió un error al comunicarse con el servidor.');
    error.codigo = info.code || 'ERROR_INTERNO';
    error.fields = info.fields || {};
    error.http = respuesta.status;
    return error;
  }

  function pedir(metodo, ruta, cuerpo) {
    asegurarClave();

    var opciones = {
      method: metodo,
      headers: { 'X-API-Key': clave, 'Accept': 'application/json' },
    };
    if (cuerpo !== undefined) {
      opciones.headers['Content-Type'] = 'application/json';
      opciones.body = JSON.stringify(cuerpo);
    }

    return fetch(BASE + ruta, opciones).then(function (respuesta) {
      return respuesta.json().catch(function () { return null; }).then(function (datos) {
        if (respuesta.status === 401) {
          guardarClave(''); // clave inválida: se pedirá de nuevo
        }
        if (!respuesta.ok) {
          throw extraerError(respuesta, datos);
        }
        return datos;
      });
    });
  }

  function conQuery(ruta, parametros) {
    var partes = [];
    Object.keys(parametros).forEach(function (claveParam) {
      var valor = parametros[claveParam];
      if (valor !== null && valor !== undefined && valor !== '') {
        partes.push(encodeURIComponent(claveParam) + '=' + encodeURIComponent(valor));
      }
    });
    return partes.length ? ruta + '?' + partes.join('&') : ruta;
  }

  return {
    obtenerClave: obtenerClave,
    solicitarClave: solicitarClave,
    listarTickets: function (parametros) { return pedir('GET', conQuery('/tickets', parametros)); },
    verTicket: function (numero) { return pedir('GET', '/tickets/' + encodeURIComponent(numero)); },
    resumen: function () { return pedir('GET', '/resumen'); },
    usuarios: function (codigo) { return pedir('GET', '/usuarios/' + encodeURIComponent(codigo)); },
    tecnicos: function () { return pedir('GET', '/tecnicos'); },
    categorias: function () { return pedir('GET', '/categorias'); },
    crearTicket: function (datos) { return pedir('POST', '/tickets', datos); },
    cambiarEstado: function (numero, datos) {
      return pedir('PATCH', '/tickets/' + encodeURIComponent(numero) + '/estado', datos);
    },
    reasignarTecnico: function (numero, datos) {
      return pedir('PATCH', '/tickets/' + encodeURIComponent(numero) + '/tecnico', datos);
    },
  };
})();
