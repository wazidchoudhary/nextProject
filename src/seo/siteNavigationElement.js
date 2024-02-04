export const siteNavigationElement = () => {
    return JSON.stringify({
        '@context': 'http://schema.org',
        '@type': 'siteNavigationElement',
        potentialAction: {
            '@type': 'SearchAction',
            target: 'https://www.teflonbonehorncrafts.com/search?&q={search_term_string}',
            'query-input': 'required name=search_term_string',
        },
        name: ['Home', 'Knife Handles', 'Products', 'About Us', 'Contact Us'],
        url: ['https://https://www.teflonbonehorncrafts.com/', 'https://https://www.teflonbonehorncrafts.com/knifeHandles', 'https://https://www.teflonbonehorncrafts.com/products', 'https://https://www.teflonbonehorncrafts.com/about', 'https://https://www.teflonbonehorncrafts.com/contact'],
    });
};
