export const webPageSchema = (title, description, url) => {
    return JSON.stringify({
        '@context': 'http://schema.org',
        '@type': 'WebPage',
        name: title,
        url: url,
        description: description,
        publisher: {
            '@type': 'Organization',
            name: 'As International',
        },
    });
};
