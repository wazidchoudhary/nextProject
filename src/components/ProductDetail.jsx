import React, { useEffect } from "react";
import Image from "next/image";
import { priceHelper } from "@/lib/price-helper";
// import { Splide, SplideTrack, SplideSlide } from "@splidejs/react-splide";
// import "@splidejs/react-splide/css/skyblue";
import ImageGallery from "react-image-gallery";

const ProductDetail = ({ product }) => {
    const {
        productImage,
        productId,
        productName,
        productDescription,
        productCategory,
        productSubCategory,
        productPrice,
        productOldPrice,
        productType,
        productPriceType,
        productColor,
        productQty,
        productSize,
    } = product;

    const setProductImages = () => {
        const images = [];
       
        productImage.map((image) => {
            images.push({
                original: image,
                thumbnail: image,
            });
        });
        return (productImage.length > 1) ? <ImageGallery thumbnailPosition="left" items={images} /> :  <Image src={productImage[0]} width={500} height={500} style={{ width: "100%", height: "100%", objectFit: "cover" }} /> ;
    };

    const setColor = () =>{
        return productColor.split(',').map((color)=>{
            return <option key={color} value={color}>{color}</option>; 
        })

    }

    return (
        <main className="inner-page-sec-padding pb-0">
            <div className="container">
                <div className="row pb--50">
                    <div className="col-lg-5 mb--30">{setProductImages()}</div>

                    <div className="col-lg-7">
                        <div className="product-details-description pl-lg--30 ">
                            <h3 className="title">{productName}</h3>

                            <div className="price-block" style={{ fontSize: "18px" }}>
                                <del className="price-old">{productOldPrice ? '$'+productOldPrice : ''}</del>
                                <span className="price-new">&nbsp;&nbsp;{priceHelper.getPrice(productPrice)}</span>
                            </div>
                            <hr />
                            <table className="variations" cellSpacing={0} role="presentation">
                                <tbody>
                                    <tr>
                                        <th className="label">
                                            <label htmlFor="pa_color">Color</label>
                                        </th>
                                        <td className="value">
                                            <select id="pa_color" className="hasCustomSelect">
                                                <option value="">Choose an option</option>
                                                {setColor()}
                                            </select>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th className="label">
                                            <label htmlFor="pa_dimension">Dimension</label>
                                        </th>
                                        <td className="value">
                                        <select id="dimension" className="hasCustomSelect">
                                                <option value="">Choose an option</option>
                                        </select>
                                         
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <div className="widget-block-3">
                                <span className="widget-label">Quantity</span>
                                <div className="widgets">
                                    <div className="count-input-block">
                                        <input type="number" className="form-control text-center" defaultValue={1} />
                                        <div className="count-input-btns">
                                            <button className="inc-ammount count-btn">
                                                <i className="zmdi zmdi-chevron-up" />
                                            </button>
                                            <button className="dec-ammount count-btn">
                                                <i className="zmdi zmdi-chevron-up" />
                                            </button>
                                        </div>
                                    </div>
                                    <div className="add-cart-btn">
                                        <a href className="btn btn-outlined--primary">
                                            <i className="ion-bag" />
                                            Add to Cart
                                        </a>
                                    </div>
                                    <div className="product-status">
                                        <p>
                                            <i className="zmdi zmdi-check" /> {productQty > 0 ? "In Stock" : "Out Of Stock"}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div className="widget-social-share">
                                <span className="widget-label">Share</span>
                                <div className="social-share rounded-icons transparent-bg text-start">
                                    <a href="product-details.html#" className="single-icon">
                                        <i className="fab fa-facebook-f" />
                                    </a>
                                    <a href="product-details.html#" className="single-icon">
                                        <i className="fab fa-twitter" />
                                    </a>
                                    <a href="product-details.html#" className="single-icon">
                                        <i className="fab fa-youtube" />
                                    </a>
                                    <a href="product-details.html#" className="single-icon">
                                        <i className="fab fa-google-plus-g" />
                                    </a>
                                </div>
                            </div>
                            <div className="policy-block">
                                <ul className="policy-list">
                                    <li>
                                        <div className="icon"></div>
                                        <p>Delivery policy (edit with Customer reassurance module)</p>
                                    </li>
                                    <li>
                                        <div className="icon"></div>
                                        <p>Return policy (edit with Customer reassurance module)</p>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div className="review-tab pt--55 border-top border-bottom section-margin section-padding">
                    <ul className="nav nav-tabs" id="myTab" role="tablist">
                        <li className="nav-item" role="presentation">
                            <div className="nav-link active" id="description">
                                Description
                            </div>
                        </li>
                    </ul>
                    <div className="tab-content space-db--20" id="myTabContent">
                        <div className="tab-pane fade show active" id="tab-1" role="tabpanel" aria-labelledby="description">
                            <article className="review-article" dangerouslySetInnerHTML={{ __html: productDescription }}></article>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    );
};

export default ProductDetail;
