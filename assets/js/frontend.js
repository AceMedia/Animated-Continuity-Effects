(function () {
  'use strict';

  const config = window.ACE_VIEW_TRANSITIONS || {};
  const cssVars = config.cssVars || {};
  const excludeList = Array.isArray(config.excludeList) ? config.excludeList : [];
  const sameElementRules = Array.isArray(config.sameElementRules) ? config.sameElementRules : [];
  const nameScope = typeof config.nameScope === 'string' && config.nameScope ? config.nameScope : 'post';
  const enableRootTransition = config.enableRootTransition !== false;
  const respectReducedMotion = config.respectReducedMotion !== false;
  const transitionMode = config.transitionMode || 'cross-document';
  const animationStyle = typeof config.animationStyle === 'string' && config.animationStyle ? config.animationStyle : 'fade';
  const debugEnabled = config.debug === true;
  const shellSelectors = ['main', '.wp-site-blocks', '#page', 'body'];
  let navigationGuardsSetup = false;

  const supportsViewTransitions = 'startViewTransition' in document;

  function log() {
    if (!debugEnabled || !window.console || typeof window.console.debug !== 'function') {
      return;
    }

    const args = Array.prototype.slice.call(arguments);
    args.unshift('[ACE]');
    window.console.debug.apply(window.console, args);
  }

  function isReducedMotion() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }

  function applyCssVariables() {
    const root = document.documentElement;
    Object.keys(cssVars).forEach((key) => {
      if (cssVars[key]) {
        root.style.setProperty(key, String(cssVars[key]));
      }
    });
  }

  function resetAnimationStyleClasses(root) {
    if (!root || !root.classList) {
      return;
    }

    root.classList.remove(
      'ace-view-transition-style-fade',
      'ace-view-transition-style-slide_up',
      'ace-view-transition-style-slide_right',
      'ace-view-transition-style-slide_left',
      'ace-view-transition-style-zoom',
      'ace-view-transition-style-flip_3d',
      'ace-view-transition-style-rotate'
    );
    root.classList.add('ace-view-transition-style-' + animationStyle);
    log('style class applied', animationStyle);
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

  function getPostIdFromElement(element) {
    if (!element || !element.classList) {
      return '';
    }

    const classes = Array.from(element.classList);
    for (let i = 0; i < classes.length; i += 1) {
      const match = classes[i].match(/(?:^|-)post-(\d+)(?:-|$)/);
      if (match && match[1]) {
        return match[1];
      }
    }

    if (element.id) {
      const idMatch = element.id.match(/post-(\d+)/);
      if (idMatch && idMatch[1]) {
        return idMatch[1];
      }
    }

    return '';
  }

  function slugFromText(value) {
    return slugify(String(value || '').slice(0, 80));
  }

  function getFallbackToken(element, index) {
    if (!element) {
      return 'ace-fallback-' + index;
    }

    const id = element.id ? slugify(element.id) : '';
    if (id) {
      return 'ace-' + id;
    }

    const dataId = element.getAttribute && element.getAttribute('data-id');
    if (dataId) {
      return 'ace-data-' + slugify(dataId);
    }

    const href = element.getAttribute && element.getAttribute('href');
    if (href) {
      try {
        const url = new URL(href, window.location.href);
        if (url.pathname) {
          return 'ace-href-' + slugify(url.pathname + url.search);
        }
      } catch (error) {
        return 'ace-href-' + slugify(href);
      }
    }

    const src = element.getAttribute && element.getAttribute('src');
    if (src) {
      return 'ace-src-' + slugify(src.split('/').pop() || src);
    }

    const text = element.textContent || '';
    if (text.trim()) {
      return 'ace-text-' + slugFromText(text);
    }

    return 'ace-fallback-' + index;
  }

  function getSharedNameForElement(element, rule, index, context) {
    const label = slugify(rule && rule.label ? rule.label : 'shared');
    const scope = rule && rule.scope ? slugify(rule.scope) : 'post';

    if (!element) {
      return 'ace-' + scope + '-' + label + '-' + index;
    }

    if (label === 'site-logo') {
      return 'ace-site-logo';
    }

    if (label === 'site-header') {
      return 'ace-site-header';
    }

    if (label === 'site-footer') {
      return 'ace-site-footer';
    }

    if (label === 'primary-navigation') {
      return 'ace-primary-nav';
    }

    if (label === 'entry-title' || label === 'title') {
      if (context && context.activeTitleName) {
        return context.activeTitleName;
      }
      const postId = getPostIdFromElement(element.closest('article, .wp-block-post, [class*="post-"]'));
      return postId ? 'ace-entry-title-' + postId : 'ace-entry-title-' + slugFromText(element.textContent);
    }

    if (label === 'featured-image' || label === 'featured-image-img' || label === 'featured-image-image') {
      const article = element.closest('article, .wp-block-post, [class*="post-"]');
      const postId = getPostIdFromElement(article);
      if (context && context.activeFeaturedImageName) {
        return context.activeFeaturedImageName;
      }
      if (postId) {
        return 'ace-featured-image-' + postId;
      }
      const img = element.tagName === 'IMG' ? element : element.querySelector && element.querySelector('img');
      if (img && img.getAttribute('src')) {
        return 'ace-featured-image-' + slugify(img.getAttribute('src').split('/').pop() || img.getAttribute('src'));
      }
      return 'ace-featured-image-' + index;
    }

    return getFallbackToken(element, index);
  }

  function applySharedElementNamesTo(rootOrDocument, context) {
    const root = rootOrDocument && rootOrDocument.querySelector ? rootOrDocument : document;
    const seen = new Set();
    let namedCount = 0;

    sameElementRules.forEach((rule, ruleIndex) => {
      if (!rule || !rule.selector) {
        return;
      }

      let elements = [];
      try {
        elements = Array.from(root.querySelectorAll(rule.selector));
      } catch (error) {
        return;
      }

      elements.forEach((element, elementIndex) => {
        const token = getSharedNameForElement(element, rule, elementIndex, context);
        if (seen.has(token)) {
          return;
        }
        seen.add(token);
        element.style.viewTransitionName = token;
        if ('viewTransitionClass' in element.style) {
          element.style.viewTransitionClass = 'ace-' + slugify(rule.label || 'shared-element');
        }
        namedCount += 1;
      });
    });

    if (root === document) {
      root.documentElement.classList.toggle('ace-has-active-shared-transition', namedCount > 0);
    }

    log(root === document ? 'current shared elements named' : 'incoming shared elements named', namedCount);
    return namedCount;
  }

  function applySharedElementNames() {
    return applySharedElementNamesTo(document);
  }

  function enableDocumentTransition() {
    if (!supportsViewTransitions || !enableRootTransition) {
      return;
    }

    document.documentElement.style.viewTransitionName = 'root';
  }

  function setDocumentHints() {
    const root = document.documentElement;
    root.classList.add('ace-view-transitions-enabled');
    resetAnimationStyleClasses(root);
    document.documentElement.style.setProperty('--ace-view-transition-enabled', '1');
  }

  function setupNavigationGuards() {
    if (navigationGuardsSetup) {
      return;
    }

    navigationGuardsSetup = true;

    window.addEventListener('pageswap', (event) => {
      if (!event || !event.viewTransition) {
        return;
      }

      if (hasExcludedMatch(document)) {
        event.viewTransition.skipTransition();
      }
    });

    window.addEventListener('pagereveal', (event) => {
      if (!event || !event.viewTransition) {
        return;
      }

      if (respectReducedMotion && isReducedMotion()) {
        event.viewTransition.skipTransition();
      }
    });
  }

  function isEligibleLink(anchor) {
    if (!anchor || anchor.tagName !== 'A') {
      return false;
    }

    if (anchor.target === '_blank' || anchor.hasAttribute('download')) {
      log('skip: target/download', anchor.href);
      return false;
    }

    if (anchor.closest('[aria-hidden="true"], [inert]')) {
      log('skip: hidden/inert', anchor.href);
      return false;
    }

    if (anchor.classList && anchor.classList.contains('ab-item')) {
      log('skip: admin bar', anchor.href);
      return false;
    }

    const href = anchor.getAttribute('href');
    if (!href || href.charAt(0) === '#') {
      return false;
    }

    let url;
    try {
      url = new URL(href, window.location.href);
    } catch (error) {
      return false;
    }

    if (url.origin !== window.location.origin) {
      log('skip: external URL', url.href);
      return false;
    }

    if (url.pathname === window.location.pathname && url.search === window.location.search) {
      log('skip: hash-only/same-page', url.href);
      return false;
    }

    if (/(?:^|\/)(wp-admin|wp-login\.php|wp-json)(?:\/|$)/.test(url.pathname) || /[?&](preview|customize)=/i.test(url.search + url.hash)) {
      log('skip: wp-admin/wp-login/wp-json/preview/customizer', url.href);
      return false;
    }

    if (excludeList.length) {
      for (let i = 0; i < excludeList.length; i += 1) {
        const selector = excludeList[i];
        if (!selector) {
          continue;
        }

        try {
          if (anchor.matches(selector) || anchor.closest(selector)) {
            log('skip: excluded selector', selector, url.href);
            return false;
          }
        } catch (error) {
          continue;
        }
      }
    }

    return true;
  }

  function getShellElement(doc) {
    for (let i = 0; i < shellSelectors.length; i += 1) {
      try {
        const match = doc.querySelector(shellSelectors[i]);
        if (match) {
          return match;
        }
      } catch (error) {
        // ignore invalid selectors and keep searching
      }
    }

    return doc.body || doc.documentElement;
  }

  function buildClickContext(anchor) {
    const card = anchor ? anchor.closest('article, .wp-block-post, [class*="post-"]') : null;
    const image = card && card.querySelector ? card.querySelector('.wp-post-image, .featured-image img, .post-thumbnail img') : null;
    const title = card && card.querySelector ? card.querySelector('.entry-title, .wp-block-post-title') : null;
    const postId = getPostIdFromElement(card);

    const context = {
      anchor: anchor || null,
      card: card || null,
      clickedPostId: postId || '',
      activeFeaturedImageName: image ? (postId ? 'ace-active-featured-image-' + postId : 'ace-active-featured-image') : '',
      activeTitleName: title ? (postId ? 'ace-active-entry-title-' + postId : 'ace-active-entry-title') : '',
    };

    log('click context', {
      clickedPostId: context.clickedPostId || 'none',
      hasImage: !!image,
      hasTitle: !!title,
    });

    return context;
  }

  function reinitializeFromDocument(doc) {
    applyCssVariables();
    setDocumentHints();
    enableDocumentTransition();
    applySharedElementNamesTo(doc || document);
    setupNavigationGuards();
    return doc;
  }

  function swapDocument(nextDoc, context) {
    const shell = getShellElement(nextDoc);
    const currentShell = getShellElement(document);
    const nextBody = nextDoc.body ? nextDoc.body.cloneNode(true) : null;

    if (nextDoc.title) {
      document.title = nextDoc.title;
    }

    if (shell && shell !== nextDoc.body && shell !== nextDoc.documentElement) {
      const incomingShell = shell.cloneNode(true);
      applySharedElementNamesTo(incomingShell, context);

      if (currentShell && currentShell !== document.body && currentShell !== document.documentElement) {
        currentShell.replaceWith(incomingShell);
      } else if (nextBody) {
        document.body.replaceWith(nextBody);
      } else {
        document.body.innerHTML = incomingShell.innerHTML;
      }
      log('swapped shell', shell.tagName ? shell.tagName.toLowerCase() : 'shell');
    } else if (nextBody) {
      document.body.replaceWith(nextBody);
      log('swapped body', 'body');
    } else if (shell && shell !== nextDoc.documentElement) {
      document.body.innerHTML = shell.innerHTML;
      log('swapped shell', 'innerHTML fallback');
    }

    // Inline scripts are not re-executed here. This is the first stable pass
    // for page-shell swaps and avoids duplicate execution on each navigation.
    return reinitializeFromDocument(nextDoc);
  }

  function loadPage(url, options) {
    const navOptions = options || {};
    const shouldReplaceHistory = navOptions.replaceHistory === true;
    const context = navOptions.clickedContext || null;
    return window.fetch(url.href, {
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-ACE-View-Transition': '1',
      },
    })
      .then((response) => {
        if (!response.ok) {
          throw new Error('Request failed: ' + response.status);
        }

        return response.text();
      })
      .then((html) => {
        const nextDoc = new DOMParser().parseFromString(html, 'text/html');
        applySharedElementNamesTo(document, context);
        applySharedElementNamesTo(nextDoc, context);
        if (supportsViewTransitions) {
        document.documentElement.classList.add('ace-has-active-shared-transition');
      }
        const update = () => swapDocument(nextDoc, context);

        if (supportsViewTransitions) {
          return document.startViewTransition(update).finished.then(() => ({
            url: url.href,
            title: nextDoc.title || '',
          }));
        }

        document.documentElement.classList.add('ace-view-transition-fade');
        update();
        document.documentElement.classList.remove('ace-view-transition-fade');

        return Promise.resolve({
          url: url.href,
          title: nextDoc.title || '',
        });
      })
      .then((result) => {
        if (shouldReplaceHistory) {
          window.history.replaceState({ aceViewTransition: true, url: result.url }, '', result.url);
        } else {
          window.history.pushState({ aceViewTransition: true, url: result.url }, '', result.url);
        }

        document.dispatchEvent(new CustomEvent('ace:view-transition:navigated', { detail: { url: result.url } }));
        log('navigated', result.url);
      })
      .catch((error) => {
        log('navigation failed, falling back', error && error.message ? error.message : error);
        window.location.href = url.href;
      });
  }

  function onClick(event) {
    if (
      event.defaultPrevented ||
      event.button !== 0 ||
      event.metaKey ||
      event.ctrlKey ||
      event.shiftKey ||
      event.altKey
    ) {
      return;
    }

    if (respectReducedMotion && isReducedMotion()) {
      log('skip: reduced motion');
      return;
    }

    const anchor = event.target && event.target.closest ? event.target.closest('a[href]') : null;
    if (!anchor || !isEligibleLink(anchor)) {
      return;
    }

    event.preventDefault();
    log('intercepting navigation', anchor.href);
    loadPage(new URL(anchor.getAttribute('href'), window.location.href), {
      replaceHistory: false,
      clickedContext: buildClickContext(anchor),
    });
  }

  function onPopState() {
    if (transitionMode !== 'same-document') {
      return;
    }

    const url = new URL(window.location.href);
    log('popstate', url.href);
    loadPage(url, {
      replaceHistory: true,
      clickedContext: null,
    });
  }

  function init() {
    applyCssVariables();
    setDocumentHints();
    if (supportsViewTransitions) {
      enableDocumentTransition();
      applySharedElementNames();
      setupNavigationGuards();
    }

    document.addEventListener('click', onClick, true);
    window.addEventListener('popstate', onPopState);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
