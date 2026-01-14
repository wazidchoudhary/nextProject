import { getServerSideSitemapLegacy } from 'next-sitemap';
import { FirebaseHelper } from '@/lib/firebase-helpers';

export async function getServerSideProps(ctx) {
    // Fetch all products from Firebase
    const products = await FirebaseHelper.fetchProducts() || [];
    
    const productFields = products.map((product) => ({
        loc: `https://www.vikingcraftstore.com/products/${product.productId}`,
        lastmod: new Date().toISOString(),
        changefreq: 'weekly',
        priority: 0.8,
    }));

    // Fetch knife handles categories if available
    const knifeHandles = await FirebaseHelper.fetchKnifeHandles() || [];
    
    const knifeFields = knifeHandles.map((item) => ({
        loc: `https://www.vikingcraftstore.com/knifeHandles/${encodeURIComponent(item.categoryId || item.id)}`,
        lastmod: new Date().toISOString(),
        changefreq: 'weekly',
        priority: 0.8,
    }));

    const fields = [...productFields, ...knifeFields];

    return getServerSideSitemapLegacy(ctx, fields);
}

// Default export to prevent Next.js from rendering this page
export default function Sitemap() {}
