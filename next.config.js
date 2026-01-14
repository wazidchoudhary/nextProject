/** @type {import('next').NextConfig} */
const nextConfig = {
    reactStrictMode: true,
    poweredByHeader: false,
    compress: true,
    images: {
        formats: ['image/avif', 'image/webp'],
        domains: [
            'firebasestorage.googleapis.com',
            'suboorkhan.com',
            'res.cloudinary.com',
            'www.vikingcraftstore.com',
        ],
        minimumCacheTTL: 31536000,
    },
    async headers() {
        return [
            {
                source: '/:path*',
                headers: [
                    {
                        key: 'X-DNS-Prefetch-Control',
                        value: 'on'
                    },
                    {
                        key: 'X-XSS-Protection',
                        value: '1; mode=block'
                    },
                    {
                        key: 'X-Frame-Options',
                        value: 'SAMEORIGIN'
                    },
                    {
                        key: 'X-Content-Type-Options',
                        value: 'nosniff'
                    },
                    {
                        key: 'Referrer-Policy',
                        value: 'origin-when-cross-origin'
                    },
                    {
                        key: 'Permissions-Policy',
                        value: 'camera=(), microphone=(), geolocation=()'
                    }
                ],
            },
        ];
    },
    async redirects() {
        return [
            // Redirect from old domain paths (for users who bookmarked old URLs)
            {
                source: '/teflonbonehorncrafts/:path*',
                destination: '/:path*',
                permanent: true,
            },
            // Common old domain URL patterns that might be indexed
            {
                source: '/teflonbonehorncraft/:path*',
                destination: '/:path*',
                permanent: true,
            },
            // Handle www/non-www and trailing slashes consistently
            {
                source: '/:path+/',
                destination: '/:path+',
                permanent: true,
            },
        ];
    },
    async rewrites() {
        return {
            beforeFiles: [
                // Handle sitemap for search engines
                {
                    source: '/sitemap.xml',
                    destination: '/api/sitemap',
                },
            ],
        };
    },
    webpack: (config, {}) => {
        Object.assign(config.resolve.alias, {
            react: 'preact/compat',
            'react-dom/test-utils': 'preact/test-utils',
            'react-dom': 'preact/compat',
        });
        return config;
    },
};

module.exports = nextConfig;
