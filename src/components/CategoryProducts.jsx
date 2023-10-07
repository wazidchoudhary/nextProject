import React from 'react';
import { Card } from './Card/card';
import { useRouter } from 'next/router';
import useSelector from '@/hooks/useSelector';
import { selectCartProduct } from '@/selector/cartSelector';
export const CategoryProducts = ({ categoryProducts }) => {
    const router = useRouter();
    const cartProduct = useSelector(selectCartProduct) || [];
    const cartProductIds = cartProduct.map((product) => product.id);
    const handleClick = (id) => {
        router.push(`/products/${id}`);
    };
    return (
        <section className="mt-5">
            {categoryProducts.length && (
                <div className="container">
                    <div className="section-title mb--55">
                        <h3>Other Products In The Same Category:</h3>
                        <div className="row mt-3">
                            {categoryProducts.map((p) => (
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
                                    handleClick={handleClick}
                                    addedInCart={cartProductIds.includes(p.productId)}
                                />
                            ))}
                        </div>
                    </div>
                </div>
            )}
        </section>
    );
};
