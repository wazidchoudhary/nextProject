export const breadCrumbSchema = (name, host, url) => {
    const urlName = url ? url.split('/') : '';
    const baseHost = host.replace(/\/$/, ''); // Remove trailing slash
    
    const itemListElements = [
        {
            '@type': 'ListItem',
            position: 1,
            name: 'Home',
            item: baseHost + '/'
        },
    ];

    // Build breadcrumb path from URL segments
    let currentPath = baseHost;
    let position = 2;
    
    for (let i = 3; i < urlName.length; i++) {
        if (urlName[i] && urlName[i] !== '') {
            currentPath += '/' + urlName[i];
            const isLast = i === urlName.length - 1;
            
            const listItem = {
                '@type': 'ListItem',
                position: position,
                name: decodeURIComponent(urlName[i]).replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
            };
            
            // Only add item URL if not the last item
            if (!isLast) {
                listItem.item = currentPath;
            }
            
            itemListElements.push(listItem);
            position++;
        }
    }

    return JSON.stringify({
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: itemListElements,
    });
};
