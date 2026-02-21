console.log('✅ peer-to-peer-crypto checkout.js loaded');

const settings = window.wc.wcSettings.getSetting('peer-to-peer-crypto_data', {});
const label =
  window.wp.htmlEntities.decodeEntities(settings.title) ||
  window.wp.i18n.__('PEER TO PEER CRYPTO', 'epeer-to-peer-crypto');

const Content = () => {
  const html = window.wp.htmlEntities.decodeEntities(settings.description || '');

  // Prefer RawHTML when available (WP element), otherwise fall back to dangerouslySetInnerHTML.
  const el = window.wp.element;
  if (el && el.RawHTML) {
    return el.createElement(el.RawHTML, null, html);
  }

  return el.createElement('div', { dangerouslySetInnerHTML: { __html: html } });
};

const Block_Gateway = {
  name: 'peer-to-peer-crypto',
  label: label,
  content: Object(window.wp.element.createElement)(Content, null),
  edit: Object(window.wp.element.createElement)(Content, null),
  canMakePayment: () => true,
  ariaLabel: label,
  supports: {
    features: settings.supports,
  },
};

window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);
