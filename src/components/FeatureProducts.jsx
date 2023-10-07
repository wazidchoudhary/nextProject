import React from 'react';
import { useRouter } from 'next/router';
import { Card } from './Card/card';
import { FirebaseHelper } from '@/lib/firebase-helpers';
import useSelector from '@/hooks/useSelector';
import { selectCartProduct } from '@/selector/cartSelector';
const FeatureProducts = ({ products }) => {
    const router = useRouter();
    const cartProduct = useSelector(selectCartProduct) || [];
    const cartProductIds = cartProduct.map((product) => product.id);
    return (
        <section id="featuredProducts" className="section-margin welcome-section">
            <div className="container-lg">
                <div className="row justify-content-center section-padding border-bottom">
                    <div className="col-lg-10">
                        <div className="welcome-content">
                            <div className="section-title">
                                <h2>Featured Products</h2>
                                <div className="shop-product-wrap grid with-pagination mt-2 row space-db--30">
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
                                            addedInCart={cartProductIds.includes(p.productId)}
                                        />
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
};

export default FeatureProducts;
