import { getServerSideSitemapLegacy } from 'next-sitemap';
import { FirebaseHelper } from '@/lib/firebase-helpers';

export async function getServerSideProps(ctx) {
    try {
        // Fetch all products from Firebase using existing method
        const products = await FirebaseHelper.syncAllProducts() || [];
        
        const productFields = products.map((product) => ({
            loc: `https://www.vikingcraftstore.com/products/${product.productId}`,
            lastmod: new Date().toISOString(),
            changefreq: 'weekly',
            priority: 0.8,
        }));

        return getServerSideSitemapLegacy(ctx, productFields);
    } catch (error) {
        console.error('Error generating server sitemap:', error);
        // Return empty sitemap on error
        return getServerSideSitemapLegacy(ctx, []);
    }
}

// Default export to prevent Next.js from rendering this page
export default function Sitemap() {}
