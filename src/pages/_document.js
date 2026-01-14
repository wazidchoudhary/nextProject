import { Html, Head, Main, NextScript } from 'next/document';

export default function Document() {
    return (
        <Html lang="en">
            <Head>
                {/* External CSS from public folder */}
                <link rel="stylesheet" href="/assets/css/plugins.min.css" />
                <link rel="stylesheet" href="/assets/css/main.css" />
                
                {/* Preconnect to external domains for performance */}
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="anonymous" />
                <link rel="preconnect" href="https://firebasestorage.googleapis.com" />
                
                {/* DNS Prefetch */}
                <link rel="dns-prefetch" href="https://www.google-analytics.com" />
                <link rel="dns-prefetch" href="https://www.googletagmanager.com" />
                
                {/* Favicon and App Icons */}
                <link rel="icon" href="/favicon.ico" />
                <link rel="apple-touch-icon" sizes="180x180" href="/assets/image/logo.webp" />
                
                {/* SEO Meta Tags */}
                <meta name="application-name" content="Viking Craft Store" />
                <meta name="apple-mobile-web-app-capable" content="yes" />
                <meta name="apple-mobile-web-app-status-bar-style" content="default" />
                <meta name="apple-mobile-web-app-title" content="Viking Craft Store" />
                <meta name="mobile-web-app-capable" content="yes" />
                <meta name="msapplication-TileColor" content="#24bbdb" />
                
                {/* Geo Meta Tags for Local SEO */}
                <meta name="geo.region" content="IN-UP" />
                <meta name="geo.placename" content="Ghaziabad" />
                <meta name="geo.position" content="28.6692;77.4538" />
                <meta name="ICBM" content="28.6692, 77.4538" />
            </Head>
            <body>
                <Main />
                <NextScript />
            </body>
        </Html>
    );
}
