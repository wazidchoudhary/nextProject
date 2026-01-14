export const webPageSchema = (title, description, url) => {
    return JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'WebPage',
        name: title,
        url: url,
        description: description,
        isPartOf: {
            '@type': 'WebSite',
            name: 'Viking Craft Store',
            url: 'https://www.vikingcraftstore.com'
        },
        publisher: {
            '@type': 'Organization',
            name: 'Viking Craft Store - AS International',
            url: 'https://www.vikingcraftstore.com',
            logo: {
                '@type': 'ImageObject',
                url: 'https://www.vikingcraftstore.com/assets/image/logo.webp'
            }
        },
        inLanguage: 'en-US',
        dateModified: new Date().toISOString().split('T')[0]
    });
};
