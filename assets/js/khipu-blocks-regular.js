(function() {
    const registry = window.wc && window.wc.wcBlocksRegistry;
    const settingsApi = window.wc && window.wc.wcSettings;
    if ( ! registry || ! settingsApi ) return;

    const settings = settingsApi.getSetting( 'khipuregulartransfer_data', {} );
    const el = wp.element.createElement;

    const Label = () => el( 'span', null, settings.title || 'Khipu' );
    const Content = () => el( 'div', null, settings.description || '' );

    registry.registerPaymentMethod( {
        name: 'khipuregulartransfer',
        label: el( Label ),
        content: el( Content ),
        edit: el( Content ),
        canMakePayment: () => true,
        ariaLabel: settings.title || 'Pago con Transferencia Manual', 
        supports: { features: settings.supports || [ 'products' ] },
    } );    
})();
