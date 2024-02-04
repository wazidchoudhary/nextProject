import React, { useState } from 'react';
import Image from 'next/image';
import { priceHelper } from '@/lib/price-helper';
import { CartHelper } from '@/lib/cart';
import Link from 'next/link';
import StrUtils from '@/utils/str-utils';
export const Card = ({ content, handleClick = () => {}, layout = 'grid', addedInCart }) => {
    const { id, category, subCategory, name, priceOld, priceNew, image, description, color = '' } = content;
    const [image1, image2] = image;
    const layoutClass = layout === 'grid' ? ['', '', '', '', '', '', 'auto'] : ['product-type-list', 'list-description', 'list-buttons', '#f6f6f6', 'pt-2 pb-3', 'list-button', '250px'];

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
                <Image title={name} src={image2} width={140} loading="lazy" alt={name} height={140} style={{ width: '100%', height: '100%', objectFit: 'cover' }} />
            </div>
        ) : (
            ''
        );
    };

    const getDiscountPercent = () => {
        return isNaN(Number(priceNew)) ? '' : <span className="product-badge-2">-{parseInt(((Number(priceOld) - Number(priceNew)) / Number(priceOld)) * 100)}%</span>;
    };

    const getButtons = () => {
        return typeof priceNew === 'string' ? (
            <button onClick={handleAddToCart} disabled={addedInCart} type="button" className={`cardButton ${layoutClass[5]}`} style={addedInCart ? { backgroundColor: '#24bbdb', color: 'white' } : {}}>
                {addedInCart ? (
                    <>
                        <i className="fa fa-check"></i> ADDED
                    </>
                ) : (
                    'ADD TO CART'
                )}
            </button>
        ) : (
            <button onClick={() => handleClick(id)} type="button" className={`cardButton ${layoutClass[5]}`}>
                SELECT OPTIONS
            </button>
        );
    };

    return (
        <>
            <div className={`col-6 col-md-3 col-lg-3 col-sm-6 mb--30 ${layoutClass[4]}`} style={{ backgroundColor: layoutClass[3] }}>
                <div className={`product-card ${layoutClass[0]}`} style={{ textAlign: 'left' }} onClick={() => handleClick(id)}>
                    <div className="image" style={{ height: '200px', width: layoutClass[6], border: '1px solid #e3e3e3', backgroundColor: '#f6f6f6' }}>
                        <Image title={name} src={image1} width={140} loading="lazy" height={140} alt={name} style={{ width: '100%', height: '200px', objectFit: 'cover' }} />
                        <div className="hover-content" style={{ height: '100%' }}>
                            {showHoverImage()}
                        </div>
                        {/* {getDiscountPercent()} */}
                    </div>
                    <div className={`description-block  ${layoutClass[1]}`}>
                        <h3 className="post-title ellipsis-1">{name}</h3>
                        <div className="description-header">
                            <h5 className="description-tag text--bold">
                                {/* <Link title={category} href={`/products/${StrUtils.normalToSnake(category)}`} className="ellipsis-1"> */}
                                    {category}
                                {/* </Link> */}
                            </h5>
                        </div>

                        <p className="mb-2 price">
                            <del className="price-old">{priceOld ? '$' + priceOld : ''}</del>
                            <span className="price-new" style={{ marginLeft: '5px' }}>
                                {priceHelper.getPrice(priceNew)}
                            </span>
                        </p>

                        {layout === 'grid' ? (
                            ''
                        ) : (
                            <>
                                <p>color : {color}</p>
                                {getButtons()}
                            </>
                        )}
                    </div>
                </div>
                {layout === 'grid' ? getButtons() : ''}
            </div>
        </>
    );
};
