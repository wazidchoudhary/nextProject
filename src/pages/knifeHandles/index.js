import React, { useState } from 'react';
import { FirebaseHelper } from '@/lib/firebase-helpers';
import CommonMeta from '@/seo/MetaHead';
import { useRouter } from 'next/router';
import { Card } from '@/components/Card/card';
import BreadCrumb from '@/seo/BreadCrumb';
import { FilterSection } from '@/components/FilterSection';
import { Filters } from '@/utils/sort-filter';
import ProductSchema from '@/seo/ProductSchema';
import Container from '@/components/Layout/Container';

export default ({ products }) => {
    const [search, setSearch] = useState('');
    const [sorting, setSorting] = useState('');
    const [layout, setLayout] = useState('grid');
    const layoutClass = layout === 'grid' ? '' : 'shop-product-wrap list';
    const filterOperation = Filters(search, products, sorting);
    const router = useRouter();
    const title = 'KNIFE HANDLES';
    const description = 'Knife Handles Category Product';
    const breadCrumbItems = [{ url: '/', name: 'Home' }, { name: title }];

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
                        />
                    ))}
                </div>
            </Container>
        </>
    );
};

export const getServerSideProps = async (context) => {
    const res = await FirebaseHelper.fetchProductByKey('mainCategory', 'knifeHandles');

    return {
        props: {
            products: res,
        },
    };
};
