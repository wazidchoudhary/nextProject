import React from 'react';
import { Card } from './card';
import { useRouter } from 'next/router';
export const CategoryProducts = ({ categoryProducts }) => {
    const router  = useRouter()

    const handleClick = (id)=>{
        
        router.push(`/products/${id}`)
    }
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
                                handleClick={handleClick}
                                    
                            />
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
};
