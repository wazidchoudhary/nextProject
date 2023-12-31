import useSelector from '@/hooks/useSelector';
import { selectCartProduct } from '@/selector/cartSelector';
import { CartHelper } from '@/lib/cart';
import BreadCrumb from '@/seo/BreadCrumb';
import React, { Fragment } from 'react';
import store from '@/lib/store/store';
import { useRouter } from 'next/router';

import { EmptyProductSection } from '@/components/EmptyProductSection';
export default function () {
    const router = useRouter();
    const cartProducts = useSelector(selectCartProduct) || [];
    const title = 'Cart - AS INTERNATIONAL PRODUCTS CART';
    const breadCrumbItems = [{ url: '/', name: 'Home' }, { name: 'Cart' }];
    const totalPrice = cartProducts
        .reduce((previousValue, currentValue) => {
            return previousValue + currentValue.qty * currentValue.price;
        }, 0)
        .toFixed(2);
    const manageQuantity = (id, cal) => {
        const index = cartProducts.findIndex((prod) => prod.id == id);
        if (index != -1) {
            cartProducts[index].qty = cartProducts[index].qty + cal;
            store.dispatch({ cart: [...cartProducts] });
            localStorage.setItem('asInternationalCart', JSON.stringify([...cartProducts]));
        }
    };

    return (
        <>
            <BreadCrumb items={breadCrumbItems} text={title} />
            
            {cartProducts.length === 0 ? (
                <EmptyProductSection height={200} width={200} description="Cart Is Empty" fontSize="25px" containerHeight="auto">
                    <button type="button" onClick={() => router.push('/products')} className="btn btn-primary" style={{ marginTop: '50px' }}>
                        Shop Now
                    </button>
                </EmptyProductSection>
            ) : (
                <main className="cart-page-main-block inner-page-sec-padding-top">
                    <div className="cart_area cart-area-padding sp-inner-page--top ">
                        <div className="container">
                            <div className="row">
                                <div className="col-12">
                                    <div className="cart-table table-responsive mb--40">
                                        <table className="table">
                                            <thead>
                                                <tr>
                                                    <th className="pro-remove d-none d-md-flex">remove</th>
                                                    <th className="pro-thumbnail">Image</th>
                                                    <th className="pro-title">Product</th>
                                                    <th className="pro-price">Price</th>
                                                    <th className="pro-quantity">Quantity</th>
                                                    <th className="pro-subtotal">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {cartProducts.map((product, j) => {
                                                    return (
                                                        <Fragment>
                                                            <tr key={j}>
                                                                <td className="pro-remove d-none d-sm-table-cell" style={{ cursor: 'pointer' }} onClick={() => CartHelper.removeFromCart(product.id)}>
                                                                    <i className="far fa-trash-alt" />
                                                                </td>
                                                                <td className="pro-title d-block d-sm-none">
                                                                    <span className="d-inline">{product.name}</span>
                                                                    <span className="d-inline float-end" onClick={() => CartHelper.removeFromCart(product.id)}>
                                                                        <i className="far fa-trash-alt" />
                                                                    </span>
                                                                </td>
                                                                <td className="pro-thumbnail">
                                                                    <img src={product.image} style={{ width: '150px', height: '150px', objectFit: 'cover' }} alt="Product" />
                                                                </td>
                                                                <td className="pro-title d-none d-sm-table-cell">{product.name}</td>
                                                                <td className="pro-price">
                                                                    <span className="d-inline d-sm-none">Price</span>
                                                                    <span className="d-inline float-end float-sm-end">${product.price}</span>
                                                                </td>
                                                                <td className="pro-quantity">
                                                                    <div style={{ marginRight: 'auto', marginLeft: '50px', width: '100px' }}>
                                                                        <div className="count-input-block">
                                                                            <button
                                                                                className="minus"
                                                                                onClick={() => {
                                                                                    if (product.qty > 1) {
                                                                                        manageQuantity(product.id, -1);
                                                                                    }
                                                                                }}
                                                                                style={{ padding: '0px 20px', height: '44px' }}
                                                                            >
                                                                                -
                                                                            </button>
                                                                            <input type="number" disabled={'disabled'} className="form-control text-center" value={product.qty} min={1} />
                                                                            <button
                                                                                className="plus"
                                                                                onClick={() => {
                                                                                    manageQuantity(product.id, 1);
                                                                                }}
                                                                                style={{ padding: '0px 20px', height: '44px' }}
                                                                            >
                                                                                +
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td className="pro-subtotal">
                                                                    <span className="d-inline d-md-none">SubTotal</span>
                                                                    <span className="d-inline float-end float-sm-none">${product.price * product.qty}</span>
                                                                </td>
                                                            </tr>
                                                            <tr class="spacer d-sm-table-row d-sm-none" style={{ padding: '10px 0', border: 'none', background: 'none' }}>
                                                                <td colspan="6"></td>
                                                            </tr>
                                                        </Fragment>
                                                    );
                                                })}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className="cart-section-2 sp-inner-page--bottom">
                        <div className="container">
                            <div className="row">
                                {/* Cart Summary */}
                                <div className="col-lg-6 col-12 d-flex">
                                    <div className="cart-summary">
                                        <div className="cart-summary-wrap">
                                            <h4>
                                                <span>Cart Summary</span>
                                            </h4>

                                            <p>
                                                Sub Total <span className="text-primary">${totalPrice}</span>
                                            </p>
                                            <p className="text-primary">Shipping cost to be paid at the time of order confirmation</p>
                                            <h2>
                                                Grand Total <span className="text-primary">${totalPrice}</span>
                                            </h2>
                                        </div>
                                        <div className="cart-summary-button" style={{ marginBottom: '10px' }}>
                                            <button onClick={() => router.push('/checkout')} className="checkout-btn c-btn">
                                                Checkout
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            )}

            {/* Cart Page End */}
        </>
    );
}
