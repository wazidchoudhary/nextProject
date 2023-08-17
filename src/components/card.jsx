import React from "react";
import Image from "next/image";
export const card = () => {
    
    return (
        <div className="col-lg-3 col-sm-6 mb--30">
            <div className="product-card">
                <div className="image">
                    <Image src={"/assets/image/products/home-1/product-1.jpg"} width={100} height={100} />
                </div>
                <div className="description-block">
                    <div className="description-header">
                        <h5 className="description-tag">
                            <a href>{{Category}}--{{subCategory}}</a>
                        </h5>
                    </div>
                    <h3 className="post-title">
                        <a href="product-details.html">
                           {{productName}}
                        </a>
                    </h3>
                    <p className="mb-0 price">
                        <del className="price-old">{{priceOld}}</del>
                        <span className="price-new">{{priceNew}}</span>
                    </p>
                </div>
            </div>
        </div>
    );
};
