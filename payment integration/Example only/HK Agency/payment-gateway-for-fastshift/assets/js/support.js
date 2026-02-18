!function () {
    "use strict";
    var element = window.wp.element,
        htmlEntities = window.wp.htmlEntities,
        i18n = window.wp.i18n,
        wcBlocksRegistry = window.wc.wcBlocksRegistry,
        wcSettings = window.wc.wcSettings;
    const settingsFunction = () => {
        const settings = (0, wcSettings.getSetting)("payment-gateway-for-fastshift_data", null);
        if (!settings) throw new Error("FastShift initialization data is not available");
        return settings
    };
    var supports;
    const r = () => (0, htmlEntities.decodeEntities)(settingsFunction()?.description || "");
    (0, wcBlocksRegistry.registerPaymentMethod)({
        name: "payment-gateway-for-fastshift",
        label: (0, element.createElement)((() =>
            (0, element.createElement)("img", {src: settingsFunction()?.logo_url, alt: settingsFunction()?.title})), null),
        ariaLabel: (0, i18n.__)("FastShift payment method", "payment-gateway-for-fastshift"),
        canMakePayment: () => !0,
        content: (0, element.createElement)(r, null),
        edit: (0, element.createElement)(r, null),
        supports: {features: null !== (supports = settingsFunction()?.supports) && void 0 !== supports ? supports : []}
    })
}();