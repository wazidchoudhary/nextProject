import { useRouter } from 'next/router';
import React from 'react';

export const ProductSchema = ({ product }) => {
    const router = useRouter();
    const url = router.asPath;
    const schema = {
        '@context': 'http://schema.org/',
        '@type': 'Product',
        name: product.name,
        image: [product.image],
        description: product.description,
        brand: {
            '@type': 'Brand',
            name: 'AS International',
        },
        category: product.category,
        price: product.oldPrice,
        offers: {
            '@type': 'Offer',
            url: url,
            priceCurrency: 'USD',
            price: product.price,
            seller: {
                '@type': 'Organization',
                name: 'AS International',
            },
        },
    };

    return (
        <script
            type="application/ld+json"
            dangerouslySetInnerHTML={{
                __html: JSON.stringify(schema),
            }}
        ></script>
    );
};

export default ProductSchema;
