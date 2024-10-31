const settings = window.wc.wcSettings.getSetting('refacil_gateway_data', {});
const label = window.wp.htmlEntities.decodeEntities(settings.title) || window.wp.i18n.__('REFÁCIL PAY', 're_facil_gateway');
const Content = () => {
    return window.wp.htmlEntities.decodeEntities(settings.description ||
        'Paga con Transfiya, PSE, Nequi, Daviplata, Bancolombia, TPAGA' +
        ' y Efectivo, Refacil Pay un centralizador de pagos con múltiples soluciones.');
};
const Block_Gateway = {
    name: 're_facil_gateway',
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
