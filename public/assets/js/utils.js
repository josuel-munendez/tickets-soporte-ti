'use strict';

/**
 * Utilidades de la SPA (AGENTS.md §7). Nada de HTML crudo: todo el DOM se
 * construye con createElement/textContent, por lo que los datos de la API y
 * del usuario nunca se interpretan como marcado (prevención de XSS, §4.5).
 */
window.Utils = (function () {
  /**
   * Crea un elemento. `atributos` admite claves especiales:
   *   texto  -> textContent   |  clase -> className  |  onXxx -> addEventListener.
   * @param {string} etiqueta
   * @param {Object} [atributos]
   * @param {Array} [hijos]
   * @returns {HTMLElement}
   */
  function h(etiqueta, atributos, hijos) {
    var nodo = document.createElement(etiqueta);
    if (atributos) {
      Object.keys(atributos).forEach(function (clave) {
        var valor = atributos[clave];
        if (valor === null || valor === undefined) {
          return;
        }
        if (clave === 'texto') {
          nodo.textContent = String(valor);
        } else if (clave === 'clase') {
          nodo.className = String(valor);
        } else if (clave.indexOf('on') === 0 && typeof valor === 'function') {
          nodo.addEventListener(clave.slice(2), valor);
        } else {
          nodo.setAttribute(clave, String(valor));
        }
      });
    }
    if (hijos) {
      hijos.forEach(function (hijo) {
        if (hijo === null || hijo === undefined || hijo === false) {
          return;
        }
        nodo.append(hijo.nodeType ? hijo : document.createTextNode(String(hijo)));
      });
    }
    return nodo;
  }

  /** Vacía un nodo sin recurrir a HTML crudo. */
  function limpiar(nodo) {
    while (nodo.firstChild) {
      nodo.removeChild(nodo.firstChild);
    }
  }

  /** Escapa texto para contextos que lo requieran (defensa en profundidad). */
  function escapeHtml(texto) {
    return String(texto).replace(/[&<>"']/g, function (caracter) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[caracter];
    });
  }

  /** Retrasa la ejecución hasta que pasan `ms` sin nuevas llamadas (debounce). */
  function debounce(fn, ms) {
    var temporizador = null;
    return function () {
      var args = arguments;
      var contexto = this;
      clearTimeout(temporizador);
      temporizador = setTimeout(function () {
        fn.apply(contexto, args);
      }, ms);
    };
  }

  function porId(id) {
    return document.getElementById(id);
  }

  /** ISO 8601 (con offset) -> texto local legible en es-CO. */
  function formatearFecha(iso) {
    if (!iso) {
      return '—';
    }
    var fecha = new Date(iso);
    if (isNaN(fecha.getTime())) {
      return String(iso);
    }
    return fecha.toLocaleString('es-CO', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }

  return {
    h: h,
    limpiar: limpiar,
    escapeHtml: escapeHtml,
    debounce: debounce,
    porId: porId,
    formatearFecha: formatearFecha,
  };
})();
