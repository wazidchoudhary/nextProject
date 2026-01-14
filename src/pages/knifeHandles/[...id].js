import { FirebaseHelper } from '@/lib/firebase-helpers';
import React, { useState } from 'react';
import StrUtils from '@/utils/str-utils';
import CommonMeta from '@/seo/MetaHead';
import { Card } from '@/components/Card/card';
import { EmptyProductSection } from '@/components/EmptyProductSection';
import { useRouter } from 'next/router';
import BreadCrumb from '@/seo/BreadCrumb';
import ProductSchema from '@/seo/ProductSchema';
import { FilterSection } from '@/components/FilterSection';
import { Filters } from '@/utils/sort-filter';
import Container from '@/components/Layout/Container';
import useSelector from '@/hooks/useSelector';
import { selectCartProduct } from '@/selector/cartSelector';
import { webPageSchema } from '@/seo/webPageSchema';
import { organizationSchema } from '@/seo/organizationSchema';
import { siteNavigationElement } from '@/seo/siteNavigationElement';
import { breadCrumbSchema } from '@/seo/breadCrumbSchema';

export default function ({ products, category, url }) {
    const [search, setSearch] = useState('');
    const [sorting, setSorting] = useState('');
    const cartProduct = useSelector(selectCartProduct) || [];
    const cartProductIds = cartProduct.map((product) => product.id);
    const [layout, setLayout] = useState('grid');
    const layoutClass = layout === 'grid' ? '' : 'shop-product-wrap list';
    const filterOperation = Filters(search, products, sorting);
    const router = useRouter();
    const HOST = 'https://www.vikingcraftstore.com';
    let updatedUrl = HOST+'/knifeHandles/'+url;
    const title = `${category} - Premium Knife Handle Scales | Viking Craft Store`;
    const description = `Shop premium ${category} knife handle scales at Viking Craft Store. High-quality handcrafted knife making supplies from AS International with worldwide shipping.`;
    const breadCrumbItems = [{ url: '/', name: 'Home' }, { url: '/knifeHandles', name: 'Knife Handles' }, { name: category }];

    return (
        <>
            <CommonMeta title={title} description={description} />
            <BreadCrumb items={breadCrumbItems} text={title} />
            {products.map((p) => (
                <ProductSchema
                    product={{
                        id: p.productId,
                        name: p.productName,
                        image: p.productImage,
                        description: p.productDescription,
                        category: p.productCategory,
                        price: p.productPrice,
                        oldPrice: p.productOldPrice,
                    }}
                />
            ))}
            <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: webPageSchema(title, description, updatedUrl) }} />
            <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: organizationSchema() }} />
            <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: siteNavigationElement() }} />
            <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: breadCrumbSchema(title, HOST, updatedUrl) }} />
            {products.length > 0 ? (
                <Container>
                    <FilterSection data={{ products, search, setSearch, setSorting, setLayout, layout }} />

                    <div className={`row ${layoutClass}`}>
                        {filterOperation.map((p) => (
                            <Card
                                key={p.productId}
                                content={{
                                    id: p.productId,
                                    category: p.productCategory,
                                    subCategory: p.productSubCategory,
                                    name: p.productName,
                                    priceNew: p.productPrice,
                                    priceOld: p.productOldPrice,
                                    image: p.productImage,
                                    description: p.productDescription,
                                }}
                                handleClick={(id) => {
                                    router.push(`/products/${id}`);
                                }}
                                layout={layout}
                                addedInCart={cartProductIds.includes(p.productId)}
                            />
                        ))}
                    </div>
                </Container>
            ) : (
                <EmptyProductSection />
            )}
        </>
    );
}

export const getServerSideProps = async (context) => {
    console.log(context.query, ' @@@context');
    const url = context.query.id;
    let key = 'productCategory';
    let id = url[0];
    if (url.length > 1) {
        key = 'productSubCategory';
        id = url[1];
    }
    console.log(StrUtils.snakeToNormal(id));
    const products = await FirebaseHelper.fetchProductByKey(key, StrUtils.snakeToNormal(id));

    return {
        props: {
            products: products,
            category: StrUtils.snakeToNormal(id),
            url: url,
        },
    };
};
