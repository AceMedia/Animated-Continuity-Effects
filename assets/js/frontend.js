(function () {
  'use strict';

  const config = window.ACE_VIEW_TRANSITIONS || {};
  const cssVars = config.cssVars || {};
  const excludeList = normalizeSelectorList(config.excludeList);
  const skipTransitionList = normalizeSelectorList(config.skipTransitionList || config.skipTransitionSelectors);
  const sameElementRules = Array.isArray(config.sameElementRules) ? config.sameElementRules : [];
  const nameScope = typeof config.nameScope === 'string' && config.nameScope ? config.nameScope : 'post';
  const enableRootTransition = config.enableRootTransition !== false;
  const respectReducedMotion = config.respectReducedMotion !== false;
  const transitionMode = config.transitionMode || 'cross-document';
  const isSameDocumentMode = transitionMode === 'same-document';
  const isCrossDocumentMode = transitionMode === 'cross-document';
  const animationStyle = typeof config.animationStyle === 'string' && config.animationStyle ? config.animationStyle : 'fade';
  const interceptInternalLinks = config.interceptInternalLinks === true;
  const sameDocumentRouterEnabled = config.sameDocumentRouterEnabled === true || (isSameDocumentMode && interceptInternalLinks);
  const activeClickPairing = config.activeClickPairing === true;
  const activeFeaturedImagePairing = config.activeFeaturedImagePairing !== false;
  const debugEnabled = config.debug === true;
  const debugDisableSharedElements = config.debugDisableSharedElements === true;
  const shellSelectors = Array.isArray(config.shellSelectors) && config.shellSelectors.length
    ? config.shellSelectors
    : ['main', '.wp-site-blocks > main', '#primary', '#content', '.site-main'];
  const persistentSelectors = normalizeSelectorList(config.persistentSelectors || [
    'header',
    '.site-header',
    '.wp-site-blocks > header',
    'nav',
    '.wp-block-navigation',
    'footer',
    '.site-footer',
    '.wp-site-blocks > footer',
  ]);
  let navigationGuardsSetup = false;
  const invalidExclusionSelectors = new Set();
  const invalidPersistentSelectors = new Set();
  const invalidSkipTransitionSelectors = new Set();

  const supportsViewTransitions = 'startViewTransition' in document;

  function log() {
    if (!debugEnabled || !window.console || typeof window.console.debug !== 'function') {
      return;
    }

    const args = Array.prototype.slice.call(arguments);
    args.unshift('[ACE]');
    window.console.debug.apply(window.console, args);
  }

  function normalizeSelectorList(value) {
    if (Array.isArray(value)) {
      return value.map(String).map((item) => item.trim()).filter(Boolean);
    }

    return String(value || '')
      .split(/[\n,]+/)
      .map((item) => item.trim())
      .filter(Boolean);
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

  function hasSkipTransitionMatch(root) {
    if (!root || !skipTransitionList.length) {
      return false;
    }

    return skipTransitionList.some((selector) => {
      try {
        return !!root.querySelector(selector);
      } catch (error) {
        if (!invalidSkipTransitionSelectors.has(selector)) {
          invalidSkipTransitionSelectors.add(selector);
          log('invalid skip-transition selector', selector, error);
        }
        return false;
      }
    });
  }

  function getExclusionSelectorForElement(element) {
    if (!element || !element.matches || !excludeList.length) {
      return '';
    }

    for (let i = 0; i < excludeList.length; i += 1) {
      const selector = excludeList[i];
      try {
        if (element.matches(selector) || !!element.closest(selector)) {
          return selector;
        }
      } catch (error) {
        if (!invalidExclusionSelectors.has(selector)) {
          invalidExclusionSelectors.add(selector);
          log('invalid exclusion selector', selector, error);
        }
      }
    }

    return '';
  }

  function isExcludedElement(element) {
    return !!getExclusionSelectorForElement(element);
  }

  function getPersistentSelectorForElement(element) {
    if (!element || !element.matches || !persistentSelectors.length) {
      return '';
    }

    for (let i = 0; i < persistentSelectors.length; i += 1) {
      const selector = persistentSelectors[i];
      try {
        if (element.matches(selector) || !!element.closest(selector)) {
          return selector;
        }
      } catch (error) {
        if (!invalidPersistentSelectors.has(selector)) {
          invalidPersistentSelectors.add(selector);
          log('invalid persistent selector', selector, error);
        }
      }
    }

    return '';
  }

  function isPersistentElement(element) {
    return !!getPersistentSelectorForElement(element);
  }

  function clearElementAceTransitionName(element) {
    if (!element || !element.style) {
      return;
    }

    if (element.style.viewTransitionName && element.style.viewTransitionName.indexOf('ace-') === 0) {
      element.style.viewTransitionName = '';
    }

    if ('viewTransitionClass' in element.style) {
      element.style.viewTransitionClass = '';
    }
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

  function getTextToken(element) {
    if (!element) {
      return '';
    }

    const text = element.textContent || '';
    return text.trim() ? slugFromText(text) : '';
  }

  function getImageSrcToken(element) {
    if (!element || !element.getAttribute) {
      return '';
    }

    const src = element.getAttribute('src') || element.getAttribute('data-src') || element.getAttribute('data-lazy-src') || '';
    if (!src) {
      return '';
    }

    try {
      const url = new URL(src, window.location.href);
      return slugify(url.pathname.split('/').pop() || url.pathname || src);
    } catch (error) {
      return slugify(src.split('/').pop() || src);
    }
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

  function getRootElement(rootOrDocument) {
    if (!rootOrDocument) {
      return document;
    }

    if (rootOrDocument.documentElement) {
      return rootOrDocument.documentElement;
    }

    return rootOrDocument;
  }

  function clearAceViewTransitionNames(rootOrDocument) {
    const root = rootOrDocument && rootOrDocument.querySelectorAll ? rootOrDocument : document;
    root.querySelectorAll('[style*="view-transition-name"]').forEach((element) => {
      if (element.style && element.style.viewTransitionName && element.style.viewTransitionName.indexOf('ace-') === 0) {
        element.style.viewTransitionName = '';
      }

      if (element.style && 'viewTransitionClass' in element.style) {
        element.style.viewTransitionClass = '';
      }

      if (element.removeAttribute) {
        element.removeAttribute('data-ace-original-view-transition-name');
      }
    });
  }

  function findDuplicateTransitionNames(rootOrDocument) {
    const root = rootOrDocument && rootOrDocument.querySelectorAll ? rootOrDocument : document;
    const names = new Map();
    const duplicates = [];

    root.querySelectorAll('*').forEach((element) => {
      const name = element.style && element.style.viewTransitionName;
      if (!name || name === 'none') {
        return;
      }

      if (names.has(name)) {
        duplicates.push(name);
      }
      names.set(name, element);
    });

    return Array.from(new Set(duplicates));
  }

  function countViewTransitionName(rootOrDocument, name) {
    const root = rootOrDocument && rootOrDocument.querySelectorAll ? rootOrDocument : document;
    let count = 0;

    if (!name) {
      return count;
    }

    root.querySelectorAll('*').forEach((element) => {
      if (element.style && element.style.viewTransitionName === name) {
        count += 1;
      }
    });

    return count;
  }

  function elementsWithTransitionName(rootOrDocument, name) {
    const root = rootOrDocument && rootOrDocument.querySelectorAll ? rootOrDocument : document;
    const matches = [];

    if (!name) {
      return matches;
    }

    root.querySelectorAll('*').forEach((element) => {
      if (element.style && element.style.viewTransitionName === name) {
        matches.push(element);
      }
    });

    return matches;
  }

  function enforceSingleTransitionName(rootOrDocument, name, preferredElement) {
    const matches = elementsWithTransitionName(rootOrDocument, name);
    matches.forEach((element) => {
      if (element !== preferredElement) {
        element.style.viewTransitionName = '';
      }
    });

    return preferredElement && preferredElement.style && preferredElement.style.viewTransitionName === name ? 1 : 0;
  }

  function countNamedElements(rootOrDocument) {
    const root = rootOrDocument && rootOrDocument.querySelectorAll ? rootOrDocument : document;
    let count = 0;

    root.querySelectorAll('*').forEach((element) => {
      if (element.style && element.style.viewTransitionName && element.style.viewTransitionName !== 'none') {
        count += 1;
      }
    });

    return count;
  }

  function removeViewTransitionName(rootOrDocument, name) {
    const root = rootOrDocument && rootOrDocument.querySelectorAll ? rootOrDocument : document;

    if (!name) {
      return;
    }

    root.querySelectorAll('*').forEach((element) => {
      if (!element.style || element.style.viewTransitionName !== name) {
        return;
      }

      if (element.hasAttribute && element.hasAttribute('data-ace-original-view-transition-name')) {
        element.style.viewTransitionName = element.getAttribute('data-ace-original-view-transition-name') || '';
        element.removeAttribute('data-ace-original-view-transition-name');
        return;
      }

      element.style.viewTransitionName = '';
    });
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
      const postId = getPostIdFromElement(element.closest('article, .wp-block-post, [class*="post-"]'));
      const token = getTextToken(element);
      return postId ? 'ace-entry-title-' + postId : 'ace-entry-title-' + (token || index);
    }

    if (label === 'featured-image' || label === 'featured-image-img' || label === 'featured-image-image') {
      const article = element.closest('article, .wp-block-post, [class*="post-"]');
      const postId = getPostIdFromElement(article);
      if (postId) {
        return 'ace-featured-image-' + postId;
      }
      const img = element.tagName === 'IMG' ? element : element.querySelector && element.querySelector('img');
      const srcToken = getImageSrcToken(img || element);
      if (srcToken) {
        return 'ace-featured-image-' + srcToken;
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
        const exclusionSelector = getExclusionSelectorForElement(element);
        if (exclusionSelector) {
          clearElementAceTransitionName(element);
          log('shared element skipped by exclusion', exclusionSelector, element);
          return;
        }

        if (sameDocumentRouterEnabled && isPersistentElement(element)) {
          clearElementAceTransitionName(element);
          log('shared element skipped because persistent in same-document mode', element);
          return;
        }

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

  function findClickedImage(anchor) {
    if (!anchor) {
      return null;
    }

    return (
      anchor.querySelector('img.wp-post-image, .wp-block-post-featured-image img, .post-thumbnail img, .featured-image img, img') ||
      (anchor.matches && anchor.matches('img') ? anchor : null) ||
      (anchor.closest && anchor.closest('figure, .wp-block-post-featured-image, .post-thumbnail, .featured-image') ? anchor.closest('figure, .wp-block-post-featured-image, .post-thumbnail, .featured-image').querySelector('img') : null) ||
      (anchor.closest && anchor.closest('article, .wp-block-post, [class*="post-"]') ? anchor.closest('article, .wp-block-post, [class*="post-"]').querySelector('img.wp-post-image, .wp-block-post-featured-image img, .post-thumbnail img, .featured-image img, img') : null)
    );
  }

  function findClickedTitle(anchor) {
    if (!anchor) {
      return null;
    }

    return (
      (anchor.closest && anchor.closest('.entry-title, .wp-block-post-title, h1, h2, h3')) ||
      anchor.querySelector('.entry-title, .wp-block-post-title, h1, h2, h3') ||
      (anchor.closest && anchor.closest('article, .wp-block-post, [class*="post-"]') ? anchor.closest('article, .wp-block-post, [class*="post-"]').querySelector('.entry-title a, .wp-block-post-title a, .entry-title, .wp-block-post-title') : null)
    );
  }

  function getClickedTitleElement(anchor) {
    const title = findClickedTitle(anchor);
    if (!title) {
      return null;
    }

    if (title.tagName === 'A') {
      return title;
    }

    return title.closest && title.closest('a') ? title.closest('a') : title;
  }

  function findPostLikeContainer(anchor) {
    return anchor ? anchor.closest('article, .wp-block-post, [class*="post-"], .wp-block-post-template__post') : null;
  }

  function getCandidatePostIdFromElement(element) {
    if (!element) {
      return '';
    }

    return getPostIdFromElement(element) || getPostIdFromElement(element.closest && element.closest('[class*="post-"]')) || '';
  }

  function buildClickContext(anchor) {
    const card = findPostLikeContainer(anchor);
    const clickedImage = findClickedImage(anchor) || (card && card.querySelector ? card.querySelector('.wp-post-image, .wp-block-post-featured-image img, .post-thumbnail img, .featured-image img, img') : null);
    const clickedTitle = getClickedTitleElement(anchor) || (card && card.querySelector ? card.querySelector('.entry-title a, .wp-block-post-title a, .entry-title, .wp-block-post-title') : null);
    const clickedPostId = getCandidatePostIdFromElement(card);
    const clickedImageSrcToken = getImageSrcToken(clickedImage);
    const clickedTitleToken = getTextToken(clickedTitle);
    const href = anchor && anchor.href ? anchor.href : '';

    const context = {
      anchor: anchor || null,
      href,
      pathname: href ? (new URL(href, window.location.href)).pathname : '',
      card: card || null,
      clickedPostId: clickedPostId || '',
      clickedImage: clickedImage || null,
      clickedImageSrcToken: clickedImageSrcToken || '',
      clickedTitle: clickedTitle || null,
      clickedTitleToken: clickedTitleToken || '',
      activeFeaturedImageName: 'ace-active-featured-image',
      activeTitleName: 'ace-active-entry-title',
    };

    log('click context', {
      clickedPostId: context.clickedPostId || 'none',
      hasImage: !!context.clickedImage,
      hasTitle: !!context.clickedTitle,
      imageToken: context.clickedImageSrcToken || 'none',
      titleToken: context.clickedTitleToken || 'none',
    });

    return context;
  }

  function findCandidateFeaturedImage(root, context) {
    const scope = root && root.querySelector ? root : document;
    let candidates = [];

    try {
      candidates = Array.from(scope.querySelectorAll('.wp-post-image, .wp-block-post-featured-image img, .post-thumbnail img, .featured-image img, article img.wp-post-image, article img, img'))
        .filter((element) => {
          const excluded = getExclusionSelectorForElement(element);
          if (excluded) {
            log('featured image candidate skipped by exclusion', excluded, element);
            return false;
          }
          return true;
        });
    } catch (error) {
      candidates = [];
    }

    if (!candidates.length) {
      return null;
    }

    if (context && context.clickedPostId) {
      const postMatch = candidates.find((element) => {
        const article = element.closest('article, .wp-block-post, [class*="post-"]');
        return getCandidatePostIdFromElement(article) === context.clickedPostId;
      });
      if (postMatch) {
        return postMatch;
      }
    }

    if (context && context.clickedImageSrcToken) {
      const srcMatch = candidates.find((element) => getImageSrcToken(element) === context.clickedImageSrcToken);
      if (srcMatch) {
        return srcMatch;
      }
    }

    const prioritySelectors = [
      '.wp-post-image',
      '.wp-block-post-featured-image img',
      '.post-thumbnail img',
      '.featured-image img',
      'article img.wp-post-image',
      'article img',
      'img',
    ];

    for (let i = 0; i < prioritySelectors.length; i += 1) {
      const match = candidates.find((element) => element.matches && element.matches(prioritySelectors[i]));
      if (match) {
        return match;
      }
    }

    return candidates[0] || null;
  }

  function findCandidateTitle(root, context) {
    const scope = root && root.querySelector ? root : document;
    let candidates = [];

    try {
      candidates = Array.from(scope.querySelectorAll('main h1.entry-title, main h1.wp-block-post-title, main .entry-title, main .wp-block-post-title, main h1, article h1.entry-title, article h1.wp-block-post-title, article .entry-title, article .wp-block-post-title, article h1'));
    } catch (error) {
      candidates = [];
    }

    if (!candidates.length) {
      return null;
    }

    if (context && context.clickedTitleToken) {
      const titleMatch = candidates.find((element) => getTextToken(element) === context.clickedTitleToken);
      if (titleMatch) {
        return titleMatch;
      }
    }

    return candidates[0] || null;
  }

  function assignTransitionNameOnce(root, name, preferredElement, selectorList) {
    if (!root || !name || !preferredElement) {
      return false;
    }

    const seen = [];
    selectorList.forEach((selector) => {
      try {
        seen.push.apply(seen, Array.from(root.querySelectorAll(selector)));
      } catch (error) {
        // ignore invalid selector
      }
    });

    let assigned = false;
    seen.forEach((element) => {
      if (element.style && element.style.viewTransitionName === name) {
        if (element !== preferredElement) {
          element.style.viewTransitionName = '';
        } else {
          assigned = true;
        }
      }
    });

    if (preferredElement.style) {
      preferredElement.style.viewTransitionName = name;
      assigned = true;
    }

    return assigned;
  }

  function assignActiveTransitionName(element, name) {
    if (!element || !element.style || !name) {
      return false;
    }

    const exclusionSelector = getExclusionSelectorForElement(element);
    if (exclusionSelector) {
      log('active transition skipped by exclusion', exclusionSelector, name, element);
      return false;
    }

    if (sameDocumentRouterEnabled && isPersistentElement(element)) {
      log('active transition skipped because persistent in same-document mode', name, element);
      return false;
    }

    if (element.setAttribute && !element.hasAttribute('data-ace-original-view-transition-name')) {
      element.setAttribute('data-ace-original-view-transition-name', element.style.viewTransitionName || '');
    }

    element.style.viewTransitionName = name;
    return true;
  }

  function applyActiveClickPairNames(rootOrDocument, context, phase) {
    const root = rootOrDocument && rootOrDocument.querySelector ? rootOrDocument : document;
    if (!context) {
      return { featured: 0, title: 0 };
    }

    const result = { featured: 0, title: 0 };

    if (phase === 'current') {
      if (context.clickedImage && root.contains && root.contains(context.clickedImage)) {
        result.featured = assignActiveTransitionName(context.clickedImage, context.activeFeaturedImageName) ? 1 : 0;
      }

      const titleElement = context.clickedTitle && context.clickedTitle.matches && context.clickedTitle.matches('a')
        ? context.clickedTitle.closest('.entry-title, .wp-block-post-title, h1, h2, h3') || context.clickedTitle
        : context.clickedTitle;

      if (titleElement && root.contains && root.contains(titleElement)) {
        result.title = assignActiveTransitionName(titleElement, context.activeTitleName) ? 1 : 0;
      }

      return result;
    }

    if (phase === 'incoming') {
      const featured = findCandidateFeaturedImage(root, context);
      if (featured) {
        result.featured = assignActiveTransitionName(featured, context.activeFeaturedImageName) ? 1 : 0;
      }

      const title = findCandidateTitle(root, context);
      if (title) {
        result.title = assignActiveTransitionName(title, context.activeTitleName) ? 1 : 0;
      }
    }

    return result;
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

      if (hasSkipTransitionMatch(document)) {
        log('pageswap skipped by skip-transition selector');
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

    const excludedBy = getExclusionSelectorForElement(anchor);
    if (excludedBy) {
      log('skip: excluded selector', excludedBy, url.href);
      return false;
    }

    return true;
  }

  function getShellElement(doc) {
    for (let i = 0; i < shellSelectors.length; i += 1) {
      const selector = shellSelectors[i];
      try {
        const match = doc.querySelector(selector);
        if (match) {
          return { element: match, selector };
        }
      } catch (error) {
        // ignore invalid selectors and keep searching
      }
    }

    return { element: null, selector: '' };
  }

  function reinitializeFromDocument(scope) {
    applyCssVariables();
    setDocumentHints();
    enableDocumentTransition();

    if (!debugDisableSharedElements) {
      const shell = getShellElement(document);
      const sharedScope = scope && scope.querySelector ? scope : shell.element || document;
      applySharedElementNamesTo(sharedScope, null);
    }

    setupNavigationGuards();
    return scope;
  }

  function countActivePairNames(root, context) {
    return {
      featured: context && context.activeFeaturedImageName ? countViewTransitionName(root, context.activeFeaturedImageName) : 0,
      title: context && context.activeTitleName ? countViewTransitionName(root, context.activeTitleName) : 0,
    };
  }

  function validateActiveClickPairs(currentRoot, incomingRoot, context, currentPair, incomingPair) {
    const counts = {
      current: countActivePairNames(currentRoot, context),
      incoming: countActivePairNames(incomingRoot, context),
    };

    if (!context || !activeClickPairing) {
      return counts;
    }

    if ((currentPair.featured || incomingPair.featured) && (counts.current.featured !== 1 || counts.incoming.featured !== 1)) {
      log('active featured pair incomplete, falling back to generic names', counts);
      removeViewTransitionName(currentRoot, context.activeFeaturedImageName);
      removeViewTransitionName(incomingRoot, context.activeFeaturedImageName);
    }

    if ((currentPair.title || incomingPair.title) && (counts.current.title !== 1 || counts.incoming.title !== 1)) {
      log('active title pair incomplete, falling back to generic names', counts);
      removeViewTransitionName(currentRoot, context.activeTitleName);
      removeViewTransitionName(incomingRoot, context.activeTitleName);
    }

    return {
      current: countActivePairNames(currentRoot, context),
      incoming: countActivePairNames(incomingRoot, context),
    };
  }

  function applyActiveFeaturedImagePairNames(currentRoot, incomingRoot, context) {
    const result = {
      current: 0,
      incoming: 0,
    };

    if (!context || !activeFeaturedImagePairing || !context.activeFeaturedImageName) {
      return result;
    }

    const name = context.activeFeaturedImageName;
    const sourceImage = context.clickedImage || null;

    if (!sourceImage || !(currentRoot.contains && currentRoot.contains(sourceImage))) {
      log('active featured pairing skipped: missing clicked source image');
      return result;
    }

    if (isExcludedElement(sourceImage)) {
      log('active featured pairing skipped: source excluded', getExclusionSelectorForElement(sourceImage), sourceImage);
      return result;
    }

    const destinationImage = findCandidateFeaturedImage(incomingRoot, context);
    if (!destinationImage) {
      log('active featured pairing skipped: missing destination image');
      return result;
    }

    if (isExcludedElement(destinationImage)) {
      log('active featured pairing skipped: destination excluded', getExclusionSelectorForElement(destinationImage), destinationImage);
      return result;
    }

    assignActiveTransitionName(sourceImage, name);
    assignActiveTransitionName(destinationImage, name);

    result.current = enforceSingleTransitionName(currentRoot, name, sourceImage);
    result.incoming = enforceSingleTransitionName(incomingRoot, name, destinationImage);

    if (result.current !== 1 || result.incoming !== 1) {
      log('active featured pair incomplete, falling back to generic names', result);
      removeViewTransitionName(currentRoot, name);
      removeViewTransitionName(incomingRoot, name);
      result.current = 0;
      result.incoming = 0;
    }

    log('active featured pair counts', result);
    return result;
  }

  function swapDocument(currentShellElement, preparedIncomingShell, nextDoc, shellSelector) {
    if (nextDoc.title) {
      document.title = nextDoc.title;
    }

    if (preparedIncomingShell && currentShellElement) {
      currentShellElement.replaceWith(preparedIncomingShell);
      log('swapped shell', shellSelector || 'main');
    } else {
      throw new Error('Missing main shell in navigation target');
    }

    return nextDoc;
  }

  function getNamedElements(rootOrDocument) {
    const root = rootOrDocument && rootOrDocument.querySelectorAll ? rootOrDocument : document;
    const named = [];

    root.querySelectorAll('*').forEach((element) => {
      if (element.style && element.style.viewTransitionName && element.style.viewTransitionName !== 'none') {
        named.push({
          element,
          name: element.style.viewTransitionName,
        });
      }
    });

    return named;
  }

  function getExcludedMatches(rootOrDocument) {
    const root = rootOrDocument && rootOrDocument.querySelectorAll ? rootOrDocument : document;
    const matches = [];

    if (!excludeList.length) {
      return matches;
    }

    root.querySelectorAll('*').forEach((element) => {
      const selector = getExclusionSelectorForElement(element);
      if (selector) {
        matches.push({
          element,
          selector,
        });
      }
    });

    return matches;
  }

  function getPersistentMatches(rootOrDocument) {
    const root = rootOrDocument && rootOrDocument.querySelectorAll ? rootOrDocument : document;
    const matches = [];

    if (!persistentSelectors.length) {
      return matches;
    }

    root.querySelectorAll('*').forEach((element) => {
      const selector = getPersistentSelectorForElement(element);
      if (selector) {
        matches.push({
          element,
          selector,
        });
      }
    });

    return matches;
  }

  function exposeDebugUtility() {
    if (!debugEnabled) {
      return;
    }

    window.ACE_VIEW_TRANSITIONS_DEBUG = {
      isExcludedElement,
      elementsWithTransitionName,
      getNamedElements,
      getExcludedMatches,
      getPersistentMatches,
    };
  }

  function loadPage(url, options) {
    const navOptions = options || {};
    const shouldReplaceHistory = navOptions.replaceHistory === true;
    const context = navOptions.clickedContext || null;
    const historyState = navOptions.historyState || null;
    const source = navOptions.source || (shouldReplaceHistory ? 'popstate' : 'click');
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
        const nextShell = getShellElement(nextDoc);
        const currentShell = getShellElement(document);
        if (!nextShell.element || !currentShell.element) {
          throw new Error('Missing main shell in navigation target');
        }

        const nextShellClone = nextShell.element.cloneNode(true);
        const currentScope = currentShell.element;
        const incomingScope = nextShellClone;
        const shellName = nextShell.selector || currentShell.selector;

        clearAceViewTransitionNames(document);
        clearAceViewTransitionNames(incomingScope);

        applyCssVariables();
        setDocumentHints();
        enableDocumentTransition();

        if (!debugDisableSharedElements) {
          applySharedElementNamesTo(currentScope, null);
        }
        const currentPair = context && activeClickPairing && !debugDisableSharedElements
          ? applyActiveClickPairNames(currentScope, context, 'current')
          : { featured: 0, title: 0 };
        const currentDuplicates = debugDisableSharedElements ? [] : findDuplicateTransitionNames(currentScope);
        if (currentDuplicates.length) {
          log('duplicate transition names', currentDuplicates);
        }

        if (!debugDisableSharedElements) {
          applySharedElementNamesTo(incomingScope, null);
        }
        const incomingPair = context && activeClickPairing && !debugDisableSharedElements
          ? applyActiveClickPairNames(incomingScope, context, 'incoming')
          : { featured: 0, title: 0 };

        const activeFeaturedPair = !debugDisableSharedElements
          ? applyActiveFeaturedImagePairNames(currentScope, incomingScope, context)
          : { current: 0, incoming: 0 };
        const incomingDuplicates = debugDisableSharedElements ? [] : findDuplicateTransitionNames(incomingScope);
        if (incomingDuplicates.length) {
          log('duplicate transition names', incomingDuplicates);
        }

        const activeCounts = validateActiveClickPairs(currentScope, incomingScope, context, currentPair, incomingPair);
        const incomingShell = incomingScope;
        const currentActiveCount = activeCounts.current;
        const incomingActiveCount = activeCounts.incoming;
        currentActiveCount.featured = Math.max(currentActiveCount.featured, activeFeaturedPair.current);
        incomingActiveCount.featured = Math.max(incomingActiveCount.featured, activeFeaturedPair.incoming);
        const currentNamedCount = debugDisableSharedElements ? 0 : countNamedElements(currentScope);
        const incomingNamedCount = debugDisableSharedElements ? 0 : countNamedElements(incomingScope);
        const currentExcludedCount = getExcludedMatches(currentScope).length;
        const incomingExcludedCount = getExcludedMatches(incomingScope).length;
        const currentPersistentCount = getPersistentMatches(currentScope).length;
        const incomingPersistentCount = getPersistentMatches(incomingScope).length;
        const hasSharedTransition = currentNamedCount > 0 || incomingNamedCount > 0;

        document.documentElement.classList.toggle('ace-has-active-shared-transition', hasSharedTransition);

        log('navigation mode', context && activeClickPairing ? 'click-active' : 'generic', source, url.href);
        log('transition scopes', {
          currentScopeTag: currentScope.tagName,
          currentScopeClass: currentScope.className,
          incomingScopeTag: incomingScope.tagName,
          incomingScopeClass: incomingScope.className,
          shellSelector: shellName,
          currentNamedCount,
          incomingNamedCount,
          currentExcludedCount,
          incomingExcludedCount,
          currentPersistentCount,
          incomingPersistentCount,
        });
        log('shared transition summary', {
          shellSelector: shellName,
          currentGenericDuplicates: currentDuplicates,
          incomingGenericDuplicates: incomingDuplicates,
          current: currentActiveCount,
          incoming: incomingActiveCount,
          activeFeaturedPair,
        });

        const update = () => swapDocument(currentScope, incomingShell, nextDoc, shellName);

        if (supportsViewTransitions) {
          return document.startViewTransition(update).finished.then(() => {
            const shell = getShellElement(document);
            reinitializeFromDocument(shell.element || document);
            document.documentElement.classList.remove('ace-has-active-shared-transition');
            return {
              url: url.href,
              title: nextDoc.title || '',
            };
          }).then((result) => ({
            url: url.href,
            title: nextDoc.title || '',
          }));
        }

        document.documentElement.classList.add('ace-view-transition-fade');
        update();
        document.documentElement.classList.remove('ace-view-transition-fade');
        const shell = getShellElement(document);
        reinitializeFromDocument(shell.element || document);
        document.documentElement.classList.remove('ace-has-active-shared-transition');

        return Promise.resolve({
          url: url.href,
          title: nextDoc.title || '',
        });
      })
      .then((result) => {
        if (shouldReplaceHistory) {
          window.history.replaceState(Object.assign({ aceViewTransition: true, url: result.url }, historyState || {}), '', result.url);
        } else {
          window.history.pushState(Object.assign({ aceViewTransition: true, url: result.url }, historyState || {}), '', result.url);
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
    if (!sameDocumentRouterEnabled) {
      log('skip click interception: transition mode is', transitionMode);
      return;
    }

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
    const clickedUrl = new URL(anchor.getAttribute('href'), window.location.href);
    const clickedContext = activeClickPairing || activeFeaturedImagePairing ? buildClickContext(anchor) : null;
    loadPage(clickedUrl, {
      source: 'click',
      replaceHistory: false,
      clickedContext,
      historyState: {
        fromUrl: window.location.href,
      },
    });
  }

  function onPopState(event) {
    if (!sameDocumentRouterEnabled) {
      return;
    }

    const url = new URL(window.location.href);
    log('popstate', url.href);
    loadPage(url, {
      source: 'popstate',
      replaceHistory: true,
      clickedContext: null,
      historyState: event && event.state ? event.state : null,
    });
  }

  function init() {
    applyCssVariables();
    setDocumentHints();
    document.documentElement.setAttribute('data-ace-transition-mode', transitionMode);
    document.documentElement.setAttribute('data-ace-router', sameDocumentRouterEnabled ? 'same-document' : 'native');
    log('transition mode', transitionMode);
    log('exclusion selector count', excludeList.length);
    log('skip-transition selector count', skipTransitionList.length);
    log('persistent selector count', persistentSelectors.length);
    exposeDebugUtility();

    if (supportsViewTransitions) {
      enableDocumentTransition();
      applySharedElementNames();
      setupNavigationGuards();
    }

    if (sameDocumentRouterEnabled) {
      if (debugEnabled && window.console && typeof window.console.warn === 'function') {
        window.console.warn('[ACE] Same-document router is active. Clicks are intercepted and may not match native cross-document/history transitions.');
      }
      document.addEventListener('click', onClick, true);
      window.addEventListener('popstate', onPopState);
      log('same-document router enabled');
      return;
    }

    document.addEventListener('click', (event) => {
      const anchor = event.target && event.target.closest ? event.target.closest('a[href]') : null;
      if (anchor && debugEnabled) {
        window.console.debug('[ACE]', 'native click allowed', anchor.href);
      }
    }, true);

    if (isCrossDocumentMode) {
      log('native navigation enabled');
      return;
    }

    if (isSameDocumentMode) {
      log('same-document mode selected, internal link interception disabled');
      return;
    }

    log('unknown transition mode: native browser navigation, click interception disabled');
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init, { once: true });
  } else {
    init();
  }
})();
