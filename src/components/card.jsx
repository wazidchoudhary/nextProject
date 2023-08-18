import React from "react";
import Image from "next/image";
import { priceHelper } from "@/lib/price-helper";
export const Card = ({ content, handleClick }) => {
    const { category, subCategory, name, priceOld, priceNew, image } = content;
    const [image1,image2] = image;

    const showHoverImage = () =>{
        return image2 ? (<a href="product-details.html" className="hover-image" style={{ height: "200px" }}>
        <img src={image2}  style={{ width: "100%", height: "100%", objectFit: "cover" }}  />
        </a>) : ''
        
    }
    const price =
        typeof priceNew === "string"
            ? `$${priceNew}`
            : `$${priceHelper.lowestHighestPrice(priceNew).lowest} - $${priceHelper.lowestHighestPrice(priceNew).highest}`;
    return (
        <div className="col-lg-3 col-sm-6 mb--30">
            <div className="product-card" style={{textAlign:"left"}}>
                <div className="image" style={{ height: "200px" }}>
                    <img src={image1} style={{ width: "100%", height: "100%", objectFit: "cover" }} />
                    <div className="hover-content" style={{height:"100%"}}>
                    { showHoverImage() }
                        
                        {/* <div className="hover-btns">
                            <a href="cart.html" className="sin-btn">
                                <i className="ion-bag" />
                            </a>
                            <a href="compare.html" className="sin-btn">
                                <i className="ion-android-options" />
                            </a>
                            <a href="javascript:" className="sin-btn" data-bs-toggle="modal" data-bs-target="#quickModal">
                                <i className="ion-android-open" />
                            </a>
                        </div> */}
                    </div>

                    <span className="product-badge-2">-5%</span>
                </div>
                <div className="description-block">
                    <div className="description-header">
                        <h5 className="description-tag">
                            <a>{category.category}</a>
                        </h5>
                    </div>
                    <h3 className="post-title">
                        <a href="product-details.html">{name}</a>
                    </h3>
                    <p className="mb-0 price">
                        <del className="price-old">{priceOld}</del>
                        <span className="price-new" style={{marginLeft:"5px"}}>{price}</span>
                    </p>
                </div>
            </div>
        </div>
    );
};
