import React from "react";
import { useRouter } from "next/router";
import { Card } from "./card";
import { FirebaseHelper } from "@/lib/firebase-helpers";
const FeatureProducts = ({ products }) => {
    const router = useRouter();
    return (
        <section className="section-margin welcome-section">
            <div className="container">
                <div className="row justify-content-center section-padding border-bottom">
                    <div className="col-lg-10">
                        <div className="welcome-content">
                            <div className="section-title">
                                <h2>Featured Products</h2>
                                <div className="shop-product-wrap grid with-pagination row space-db--30">
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
            </div>
        </section>
    );
};

export default FeatureProducts;
