(function() {
    const registry = window.wc && window.wc.wcBlocksRegistry;
    const settingsApi = window.wc && window.wc.wcSettings;
    if ( ! registry || ! settingsApi ) return;

    const settings = settingsApi.getSetting( 'khipuligopay_data', {} );
    const el = wp.element.createElement;

    const Label = () => el( 'span', { style: { display: 'inline-flex', alignItems: 'center', gap: '8px' } },
        settings.icon ? el( 'img', { src: settings.icon, alt: '', style: { height: '32px', width: 'auto' } } ) : null,
        settings.title || 'Khipu'
    );
    const Content = () => el( 'div', null, settings.description || '' );

    registry.registerPaymentMethod( {
        name: 'khipuligopay',
        label: el( Label ),
        content: el( Content ),
        edit: el( Content ),
        canMakePayment: () => true,
        ariaLabel: settings.title || 'Pago con LigoPay', 
        supports: { features: settings.supports || [ 'products' ] },
    } );    
})();
