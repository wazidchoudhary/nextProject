import { FirebaseHelper } from '@/lib/firebase-helpers';
import React from 'react';
import StrUtils from '@/utils/str-utils';
import MetaHead from '@/components/MetaHead';
import { Card } from '@/components/card';
import { EmptyProductSection } from '@/components/EmptyProductSection';
import { useRouter } from 'next/router';
export default function ({ products, category, url }) {
    const router = useRouter()
    return (
        <>
            <MetaHead title={`KNIFE HANDLES - ${category}`} description={`${url.map((res) => StrUtils.snakeToNormal(res) + ' PRODUCTS')}`} />
            <section className="bg-image">
                <h2 className="sr-only">Site Breadcrumb</h2>
                <div className="container" style={{ height: '150px' }}></div>
            </section>
            {products.length > 0 ? (
                <section className="section-padding">
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
