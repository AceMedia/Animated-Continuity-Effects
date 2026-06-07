(function () {
  'use strict';

  const config = window.ACE_VIEW_TRANSITIONS || {};
  const cssVars = config.cssVars || {};
  const excludeList = Array.isArray(config.excludeList) ? config.excludeList : [];
  const sameElementRules = Array.isArray(config.sameElementRules) ? config.sameElementRules : [];
  const nameScope = typeof config.nameScope === 'string' && config.nameScope ? config.nameScope : 'post';
  const enableRootTransition = config.enableRootTransition !== false;
  const respectReducedMotion = config.respectReducedMotion !== false;

  const supportsViewTransitions = 'startViewTransition' in document && 'onpageswap' in window && 'onpagereveal' in window;

  function applyCssVariables() {
    const root = document.documentElement;
    Object.keys(cssVars).forEach((key) => {
      if (cssVars[key]) {
        root.style.setProperty(key, String(cssVars[key]));
      }
    });
  }

  function slugify(value) {
    return String(value)
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 40);
  }

  function hasExcludedMatch(root) {
    if (!root || !excludeList.length) {
      return false;
    }

    return excludeList.some((selector) => {
      try {
        return !!root.querySelector(selector);
      } catch (error) {
        return false;
      }
    });
  }

  function scopeToken(rule, index) {
    return slugify([nameScope, rule.scope || 'post', rule.label || index].join('-'));
  }

  function uniqueMatchName(base, index) {
    return 'ace-' + base + '-' + index;
  }

  function applySharedElementNames() {
    const seen = new Set();

    sameElementRules.forEach((rule, ruleIndex) => {
      if (!rule || !rule.selector) {
        return;
      }

      let elements = [];
      try {
        elements = Array.from(document.querySelectorAll(rule.selector));
      } catch (error) {
        return;
      }

      elements.forEach((element, elementIndex) => {
        const token = uniqueMatchName(scopeToken(rule, ruleIndex), elementIndex);
        if (seen.has(token)) {
          return;
        }
        seen.add(token);
        element.style.viewTransitionName = token;
        if ('viewTransitionClass' in element.style) {
          element.style.viewTransitionClass = 'ace-' + slugify(rule.label || 'shared-element');
        }
      });
    });
  }

  function enableDocumentTransition() {
    if (!supportsViewTransitions) {
      return;
    }

    if (!enableRootTransition) {
      return;
    }

    document.documentElement.style.viewTransitionName = 'root';
  }

  function shouldSkipTransition(activationElement) {
    return hasExcludedMatch(document) || hasExcludedMatch(activationElement || document);
  }

  function setDocumentHints() {
    document.documentElement.style.setProperty('--ace-view-transition-enabled', '1');
  }

  function setupNavigationGuards() {
    window.addEventListener('pageswap', (event) => {
      if (!event || !event.viewTransition) {
        return;
      }
      if (shouldSkipTransition(document)) {
        event.viewTransition.skipTransition();
      }
    });

    window.addEventListener('pagereveal', (event) => {
      if (!event || !event.viewTransition) {
        return;
      }
      if (respectReducedMotion && window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        event.viewTransition.skipTransition();
      }
    });
  }

  function init() {
    applyCssVariables();
    setDocumentHints();
    if (!supportsViewTransitions) {
      return;
    }

    enableDocumentTransition();
    applySharedElementNames();
    setupNavigationGuards();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
