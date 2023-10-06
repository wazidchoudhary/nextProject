import { FirebaseHelper } from '@/lib/firebase-helpers';
import React, { useState } from 'react';
import StrUtils from '@/utils/str-utils';
import MetaHead from '@/seo/MetaHead';
import { Card } from '@/components/Card/card';
import { EmptyProductSection } from '@/components/EmptyProductSection';
import { useRouter } from 'next/router';
import BreadCrumb from '@/seo/BreadCrumb';
import ProductSchema from '@/seo/ProductSchema';
import { FilterSection } from '@/components/FilterSection';
import { Filters } from '@/utils/sort-filter';

export default function ({ products, category, url }) {
    const [search, setSearch] = useState('');
    const [sorting, setSorting] = useState('');
    const [layout, setLayout] = useState('grid');
    const layoutClass = layout === 'grid' ? '' : 'shop-product-wrap list';
    const filterOperation = Filters(search, products, sorting);
    const router = useRouter();
    const title = `KNIFE HANDLES - ${category}`;
    const description = `${url.map((res) => StrUtils.snakeToNormal(res) + ' PRODUCTS')}`;
    const breadCrumbItems = [{ url: '/', name: 'Home' }, { url: '/knifeHandles', name: 'Knife Handles' }, { name: category }];

    return (
        <>
            <MetaHead title={title} description={description} />
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
            {products.length > 0 ? (
                <section className="section-padding mt-5">
                    <div className="container">
                        <FilterSection data={{ products, search, setSearch, setSorting, setLayout, layout }} />
                        <div className="ha-custom-tab">
                            <div className="tab-content space-db--30" id="myTabContent">
                                <div className="tab-pane fade show active" id="shop" role="tabpanel" aria-labelledby="shop-tab">
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
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
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
