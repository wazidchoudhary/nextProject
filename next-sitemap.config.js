/** @type {import('next-sitemap').IConfig} */
module.exports = {
    siteUrl: 'https://www.vikingcraftstore.com',
    generateRobotsTxt: true,
    generateIndexSitemap: true,
    changefreq: 'daily',
    priority: 0.7,
    sitemapSize: 5000,
    
    // Exclude specific paths
    exclude: [
        '/admin/*',
        '/api/*',
        '/checkout',
        '/orderDetail/*',
        '/emptyCart',
        '/cart',
        '/server-sitemap.xml',
    ],
    
    // Custom robots.txt policies
    robotsTxtOptions: {
        policies: [
            {
                userAgent: '*',
                allow: '/',
                disallow: ['/admin/', '/api/', '/checkout/', '/orderDetail/', '/emptyCart/'],
            },
            {
                userAgent: 'Googlebot',
                allow: '/',
            },
            {
                userAgent: 'Bingbot',
                allow: '/',
            },
        ],
        additionalSitemaps: [
            'https://www.vikingcraftstore.com/server-sitemap.xml',
        ],
    },
    
    // Transform function to customize sitemap entries
    transform: async (config, path) => {
        // Set higher priority for important pages
        let priority = config.priority;
        let changefreq = config.changefreq;
        
        // Homepage gets highest priority
        if (path === '/') {
            priority = 1.0;
            changefreq = 'daily';
        }
        // Product listing pages
        else if (path === '/products' || path === '/knifeHandles') {
            priority = 0.9;
            changefreq = 'daily';
        }
        // Individual product pages
        else if (path.startsWith('/products/') || path.startsWith('/knifeHandles/')) {
            priority = 0.8;
            changefreq = 'weekly';
        }
        // More products category pages
        else if (path.startsWith('/moreProducts/')) {
            priority = 0.8;
            changefreq = 'weekly';
        }
        // About and Contact
        else if (path === '/about' || path === '/contact') {
            priority = 0.6;
            changefreq = 'monthly';
        }
        // Search page
        else if (path === '/search') {
            priority = 0.5;
            changefreq = 'weekly';
        }
        // Policy pages
        else if (path === '/privacyPolicy' || path === '/termsConditions') {
            priority = 0.3;
            changefreq = 'yearly';
        }
        
        return {
            loc: path,
            changefreq,
            priority,
            lastmod: new Date().toISOString(),
            alternateRefs: [
                {
                    href: `https://www.vikingcraftstore.com${path}`,
                    hreflang: 'en',
                },
                {
                    href: `https://www.vikingcraftstore.com${path}`,
                    hreflang: 'x-default',
                },
            ],
        };
    },
};
