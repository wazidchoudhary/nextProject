import React, { useEffect, useState } from "react";
import Image from "next/image";
import { priceHelper } from "@/lib/price-helper";
import ImageGallery from "react-image-gallery";
import store from "@/lib/store/store";
import useDispatch from "@/hooks/useDispatch";
import useSelector from "@/hooks/useSelector";

const ProductDetail = ({ product,categoryProducts }) => {
    const {
        productImage,
        productId,
        productName,
        priceCategory,
        productDescription,
        productCategory,
        productSubCategory,
        productPrice,
        productOldPrice,
        productType,
        productColor,
        productQty,
        productSize,
    } = product;
    const cartData = useSelector("cart") || [];
    const dispatch = useDispatch();
    const [descriptionView, setDescriptionView] = useState(true);
    const [quantity, setQuantity] = useState(1);
    const multiPrice = typeof productPrice !== 'string';
    

    const setInitialPrice = () =>{
       const initialPrice =  multiPrice ? priceHelper.lowestHighestPrice(productPrice).lowest : productPrice
       return initialPrice
    }
    const [price,setPrice] = useState(setInitialPrice())
    
    const handleAddToCart = () => {
        store.dispatch({
            cart: [...cartData, { productName, id: productId, price: productPrice, quantity: 1 }],
        });
    };

    useEffect(() => {
        store.subscribe(({ cart }) => {
            console.log(cart, " @@@updated from product detail");
        });
    }, []);

    const setProductImages = () => {
        const images = [];

        productImage.map((image) => {
            images.push({
                original: image,
                thumbnail: image,
                originalHeight: "400",
                originalWidth: "100",
                originalAlt: productName,
                thumbnailLoading: "eager",
            });
        });
        return productImage.length > 1 ? (
            <ImageGallery thumbnailPosition="bottom" items={images} autoPlay={true} />
        ) : (
            <Image src={productImage[0]} width={100} alt={productName} height={100} style={{ width: "100%", height: "100%", objectFit: "contain" }} />
        );
    };

    const setColor = () => {
        return productColor.split(",").map((color) => {
            return (
                <option key={color} value={color}>
                    {color}
                </option>
            );
        });
    };

    const setDimensions = () =>{
        return productPrice.map((res)=>{
            return <option key={res.price} value={`${res.price}-${res.type}`}>{res.type + ` ${priceCategory.name}`}</option>

        })
    }

    const isClassActive = (view) => {
        return view ? "active" : "";
    };


    return (
        
            <div className="container-lg">
                <div className="row pb--50">
                    <div className="col-lg-5 mb--30" style={{ height: "400px" }}>
                        {setProductImages()}
                    </div>

                    <div className="col-lg-7">
                        <div className="product-details-description pl-lg--30 ">
                            <h3 className="title">{productName}</h3>

                            <div className="price-block" style={{ fontSize: "18px" }}>
                                <del className="price-old">{productOldPrice ? "$" + productOldPrice : ""}</del>
                                <span className="price-new">&nbsp;&nbsp;{priceHelper.getPrice(productPrice)}</span>
                            </div>
                            {multiPrice ? (
                                <>
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
                                                    <select id="dimension" className="hasCustomSelect" onChange={(event)=>{setPrice(event.target.value.split('-')[0])}}>
                                                        <option value="">Choose an option</option>
                                                        {setDimensions()}
                                                    </select>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </>
                            ) : (
                                ""
                            )}

                            <hr />

                            <div className="widget-block-3">
                                <span className="widget-label">Quantity</span>
                                {multiPrice ? <div className="multiPrice">${price}</div> : ''}
                                <div className="widgets" style={{position:'relative'}}>
                                    
                
                                    <div className="count-input-block">
                                    
                                    
                                        <div className="quantity" >
                                        
                                            <button
                                                className="minus"
                                                onClick={() => {
                                                    if (quantity > 1) {
                                                        setQuantity(quantity - 1);
                                                    }
                                                }}
                                            >
                                                -
                                            </button>
                                            
                                            <input
                                                type="number"
                                                id="quantity"
                                                className="qty"
                                                name="quantity"
                                                title="Qty"
                                                size={2}
                                                min={1}
                                                value={quantity}
                                                step={1}
                                                disabled={true}
                                                autoComplete="off"
                                            />
                                            <button
                                                className="plus"
                                                onClick={() => {
                                                    setQuantity(quantity + 1);
                                                }}
                                            >
                                                +
                                            </button>
                                        </div>
                                    </div>
                                    <div className="add-cart-btn">
                                        <button className="btn btn-outlined--primary" onClick={() => handleAddToCart(product)}>
                                            <i className="ion-bag" />
                                            Add to Cart
                                        </button>
                                    </div>
                                    <div className="product-status">
                                        <p>
                                            <i className="zmdi zmdi-check" /> {productQty > 0 ? "In Stock" : "Out Of Stock"}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <hr />
                            <div className="product_meta">
                                <b>Sold By</b> : AS International
                                <br />
                                <b>Categories</b> : {productCategory}, {productSubCategory}
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
                <div className="review-tab pt--55 mb--10 border-top border-bottom section-padding">
                    <ul className="nav nav-tabs" id="myTab" role="tablist" style={{ cursor: "pointer" }}>
                        <li
                            className="nav-item"
                            role="presentation"
                            onClick={() => {
                                setDescriptionView(true);
                            }}
                        >
                            <div className={`nav-link ${isClassActive(descriptionView)}`}>Description</div>
                        </li>
                        <li
                            className="nav-item"
                            role="presentation"
                            onClick={() => {
                                setDescriptionView(false);
                            }}
                        >
                            <div className={`nav-link ${isClassActive(!descriptionView)}`}>Additional Information</div>
                        </li>
                    </ul>
                    <div className="tab-content space-db--20" id="myTabContent">
                        {descriptionView === true ? (
                            <div className="">
                                
                                <article className="review-article mt-3" dangerouslySetInnerHTML={{ __html: productDescription }}></article>
                            </div>
                        ) : (
                            <div className="">
                                <h5>Additional Information</h5>
                                <table className="additionalDetail-table mt-3">
                                    <tbody>
                                        <tr>
                                            <th>COLOR</th>
                                            <td>{productColor}</td>
                                        </tr>
                                        <tr>
                                            <th>DIMENSIONS</th>
                                            <td>{multiPrice ? productPrice.map((priceType) => `${priceType.type} ${priceCategory.name}, `) : productSize}</td>
                                        </tr>
                                        <tr>
                                            <th>SIZE</th>
                                            <td>{productSize}</td>
                                        </tr>
                                        <tr>
                                            <th>TYPE</th>
                                            <td>{productType}</td>
                                        </tr>
                                        <tr>
                                            <th>CATEGORY</th>
                                            <td>{productCategory}</td>
                                        </tr>
                                        <tr>
                                            <th>SUBCATEGORY</th>
                                            <td>{productSubCategory}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </div>
            </div>
    );
};

export default ProductDetail;
