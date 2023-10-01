import React from 'react';
import { FirebaseHelper } from '@/lib/firebase-helpers';
import MetaHead from '@/components/MetaHead';
import { useRouter } from 'next/router';
import { Card } from '@/components/card';
export default ({ products }) => {
    console.log(products);
    const router = useRouter();
    return (
        <>
            <MetaHead title={'KNIFE HANDLES'} description={'Knife Handles Category Product'} />
            <section className="mt-5">
                <div className="container-lg">
                    <div className="section-title mb--55">
                        <h2>Knife Handles</h2>
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
                                        color: p.productColor,
                                    }}
                                    handleClick={(id) => {
                                        router.push(`/products/${id}`);
                                    }}
                                />
                            ))}
                        </div>
                    </div>
                </div>
            </section>
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
