import { FirebaseHelper } from '@/lib/firebase-helpers';
import React, { useEffect } from 'react';
import StrUtils from '@/utils/str-utils';
import MetaHead from '@/seo/MetaHead';
import { Card } from '@/components/card';
import { EmptyProductSection } from '@/components/EmptyProductSection';
import { useRouter } from 'next/router';
import BreadCrumb from '@/seo/BreadCrumb';

export default function ({ products, category, url }) {
    const router = useRouter();
    const title = `Products - ${category}`;
    const description = `${url.map((res) => StrUtils.snakeToNormal(res) + ' PRODUCTS')}`;
    const breadCrumbItems = [{ url: '/', name: 'Home' }, { url: '/products', name: 'Products' }, { name: category }];

    return (
        <>
            <MetaHead title={title} description={description} />
            <BreadCrumb items={breadCrumbItems} text={title} />
            {products.length > 0 ? (
                <section className="section-padding mt-5">
                    <div className="container">
                        <div className="ha-custom-tab">
                            <div className="tab-content space-db--30" id="myTabContent">
                                <div className="tab-pane fade show active" id="shop" role="tabpanel" aria-labelledby="shop-tab">
                                    <div className="row">
                                        {products.map((p) => (
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
                                                }}
                                                handleClick={(id) => {
                                                    router.push(`/products/${id}`);
                                                }}
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
