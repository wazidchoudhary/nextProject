import React from 'react'
import { Card } from './card';
export const CategoryProducts = ({categoryProducts}) => {
  return (
    <section className="mt-5">
                    <div className="container">
                        <div className="section-title mb--55">
                            <h2>Other Products In The Same Category:</h2>
                            <div className="row">
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
                                            handleClick={(id) => {
                                                router.push(`/products/${id}`);
                                            }}
                                        />
                                    ))}
                                </div>
                        </div>
                    </div>
                </section> 
  )
}
