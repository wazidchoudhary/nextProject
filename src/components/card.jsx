import React from "react";
import Image from "next/image";
import { priceHelper } from "@/lib/price-helper";
export const Card = ({content,handleClick}) => {
    const {category,subCategory,name,priceOld,priceNew,image} = content
    console.log(category,name,image,priceNew,priceOld,subCategory)

    const price = (typeof priceNew ==='string') ? `$${priceNew}` : `$${priceHelper.lowestHighestPrice(priceNew).lowest} - $${priceHelper.lowestHighestPrice(priceNew).highest}`
    return (
        <div className="col-lg-3 col-sm-6 mb--30">
            <div className="product-card">
                <div className="image">
                    <img src={image}  />
                </div>
                <div className="description-block">
                    <div className="description-header">
                        <h5 className="description-tag">
                            <a>{category.category}</a>
                        </h5>
                    </div>
                    <h3 className="post-title">
                        <a href="product-details.html">
                           {name}
                        </a>
                    </h3>
                    <p className="mb-0 price">
                        <del className="price-old">${priceOld}</del>
                        <span className="price-new">{price}</span>
                    </p>
                </div>
            </div>
        </div>
    );
};
