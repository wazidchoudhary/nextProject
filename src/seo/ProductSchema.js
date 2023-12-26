import { priceHelper } from '@/lib/price-helper';
import { useRouter } from 'next/router';
import React from 'react';

export const ProductSchema = ({ product }) => {


    const multiPrice = typeof product.price !== 'string';
    console.log(multiPrice)
    const setInitialPrice = () => {
        const initialPrice = multiPrice && product.price != 0 ? priceHelper.lowestHighestPrice(product.price).lowest : product.price;
        console.log(initialPrice)
        return Number(initialPrice); 
       
    };
    const router = useRouter();
    const url = router.asPath;
    const schema = {
        '@context': 'https://schema.org/',
        '@type': 'Product',
        name: product.name,
        image: [product.image],
        description: product.description.replace(/<\/?[^>]+(>|$)/g, ""),
        sku: `as${product.id}`,
        mpn: `${product.id}`,
        brand: {
            '@type': 'Brand',
            name: 'AS International',
        },
        category: product.category,
        offers: {
            '@type': 'Offer',
            url: url,
            priceCurrency: 'USD',
            price: setInitialPrice(),
            seller: {
                '@type': 'Organization',
                name: 'AS International',
            },
            itemCondition: 'https://schema.org/NewCondition',
            availability: 'https://schema.org/InStock',
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
