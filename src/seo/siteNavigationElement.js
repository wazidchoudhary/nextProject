export const siteNavigationElement = () => {
    const baseUrl = 'https://www.vikingcraftstore.com';
    return JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'WebSite',
        name: 'Viking Craft Store',
        url: baseUrl,
        potentialAction: {
            '@type': 'SearchAction',
            target: {
                '@type': 'EntryPoint',
                urlTemplate: `${baseUrl}/search?q={search_term_string}`
            },
            'query-input': 'required name=search_term_string'
        },
        mainEntity: {
            '@type': 'ItemList',
            itemListElement: [
                { '@type': 'SiteNavigationElement', position: 1, name: 'Home', url: `${baseUrl}/` },
                { '@type': 'SiteNavigationElement', position: 2, name: 'Knife Handles', url: `${baseUrl}/knifeHandles` },
                { '@type': 'SiteNavigationElement', position: 3, name: 'Products', url: `${baseUrl}/products` },
                { '@type': 'SiteNavigationElement', position: 4, name: 'About Us', url: `${baseUrl}/about` },
                { '@type': 'SiteNavigationElement', position: 5, name: 'Contact Us', url: `${baseUrl}/contact` }
            ]
        }
    });
};
