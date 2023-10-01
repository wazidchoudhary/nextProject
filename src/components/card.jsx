import React, { useState } from 'react';
import Image from 'next/image';
import { priceHelper } from '@/lib/price-helper';
import useSelector from '@/hooks/useSelector';
import { selectCartProduct } from '@/selector/cartSelector';
import { CartHelper } from '@/lib/cart';
export const Card = ({ content, handleClick = () => {} }) => {
    const { id, category, subCategory, name, priceOld, priceNew, image, color = '' } = content;
    const [image1, image2] = image;

    const [prod, setProd] = useState({
        quantity: 1,
        selectedPrice: '',
        selectedDimension: '',
        selectedColor: color.split(',')[0],
    });

    const handleAddToCart = () => {
        const settingProd = { ...prod, selectedPrice: priceNew };
        setProd(settingProd);
        CartHelper.addToCart({ productId: id, productName: name, productImage: image }, settingProd);
    };
    const showHoverImage = () => {
        return image2 ? (
            <div className="hover-image" style={{ height: '200px' }}>
                <Image src={image2} width={140} loading="lazy" alt={name} height={140} style={{ width: '100%', height: '100%', objectFit: 'contain' }} />
            </div>
        ) : (
            ''
        );
    };

    const getDiscountPercent = () => {
        return isNaN(Number(priceNew)) ? '' : <span className="product-badge-2">-{parseInt(((Number(priceOld) - Number(priceNew)) / Number(priceOld)) * 100)}%</span>;
    };

    return (
        <div className="col-lg-3 col-sm-6 mb--30">
            <div className="product-card" style={{ textAlign: 'left' }} onClick={() => handleClick(id)}>
                <div className="image" style={{ height: '200px', border: '1px solid #e3e3e3', backgroundColor: '#f6f6f6' }}>
                    <Image src={image1} width={140} loading="lazy" height={140} alt={name} style={{ width: '100%', height: '100%', objectFit: 'contain' }} />
                    <div className="hover-content" style={{ height: '100%' }}>
                        {showHoverImage()}

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
                    {getDiscountPercent()}
                </div>
                <div className="description-block">
                    <div className="description-header">
                        <h5 className="description-tag">
                            <a>{category}</a>
                        </h5>
                    </div>
                    <h3 className="post-title">{name}</h3>
                    <p className="mb-0 price">
                        <del className="price-old">{priceOld ? '$' + priceOld : ''}</del>
                        <span className="price-new" style={{ marginLeft: '5px' }}>
                            {priceHelper.getPrice(priceNew)}
                        </span>
                    </p>
                </div>
            </div>
            {typeof priceNew === 'string' ? (
                <button onClick={handleAddToCart} type="button" className="cardButton">
                    ADD TO CART
                </button>
            ) : (
                <button onClick={() => handleClick(id)} type="button" className="cardButton">
                    SELECT OPTIONS
                </button>
            )}
        </div>
    );
};
