import React from 'react';
import { FirebaseHelper } from '@/lib/firebase-helpers';
import MetaHead from '@/seo/MetaHead';
import { useRouter } from 'next/router';
import { Card } from '@/components/card';
import BreadCrumb from '@/seo/BreadCrumb';

export default ({ products }) => {
    const router = useRouter();
    const title = 'KNIFE HANDLES';
    const description = 'Knife Handles Category Product';
    const breadCrumbItems = [{ url: '/', name: 'Home' }, { name: title }];

    return (
        <>
            <MetaHead title={title} description={description} />
            <BreadCrumb items={breadCrumbItems} text={title} />
            <section className="mt-5">
                <div className="container-lg">
                    <div className="section-title mb--55">
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
