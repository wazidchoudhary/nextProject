import React, { useEffect } from 'react';
import store from '@/lib/store/store';
import { CartHelper } from '@/lib/cart';
import Link from 'next/link';
import EmptyCart from './EmptyCart';

const Cart = ({ cart }) => {
    const isCartEmpty = cart.length === 0;
    const totalPrice = cart
        .reduce((previousValue, currentValue) => {
            return previousValue + currentValue.qty * currentValue.price;
        }, 0)
        .toFixed(2);
    useEffect(() => {
        const fetchedFromLocalStorage = localStorage.getItem('asInternationalCart');
        store.dispatch({
            cart: fetchedFromLocalStorage ? JSON.parse(fetchedFromLocalStorage) : [],
        });
    }, []);

    return (
        <div className="sin-dropdown cart-dropdown">
            <div className="inner-single-block" style={{ maxHeight: '450px', overflowY: 'scroll' }}>
                {isCartEmpty && <EmptyCart />}
                {cart.map((cart, i) => {
                    return (
                        <div key={i} className="cart-product">
                            <div className="icon">
                                <img width={60} height={60} src={cart.image} alt={cart.name} style={{ objectFit: 'cover', maxWidth: '1000px' }} />
                                <div className="product-badge-3">{cart.qty}x</div>
                            </div>
                            <div className="description" style={{ width: '100%' }}>
                                <h4 style={{ width: '90%', whiteSpace: 'wrap' }}>{cart.name}</h4>
                                <span className="price">${cart.price}</span>
                                <ul className="attr-content" style={{ margin: '0px' }}>
                                    <li>
                                        <span>dimension :</span> {cart.dimension ? cart.dimension : 'N/A'}
                                    </li>
                                    <li>
                                        <span>color :</span> {cart.color}
                                    </li>
                                </ul>
                            </div>
                            <button type="button" className="cart-item-cross" onClick={() => CartHelper.removeFromCart(cart.id)}>
                                <i className="fas fa-times" />
                            </button>
                        </div>
                    );
                })}
            </div>
            {!isCartEmpty && (
                <>
                    <div className="inner-single-block">
                        <ul className="cart-details">
                            <li>
                                <span className="label">Subtotal</span> <span className="value">${totalPrice}</span>
                            </li>
                            <li>
                                <p className="text-primary">Shipping cost to be paid at the time of order confirmation</p>
                            </li>
                            <li>
                                <span className="label">Total</span> <span className="value">${totalPrice}</span>
                            </li>
                        </ul>
                    </div>
                    <div className="inner-single-block">
                        <Link title='Checkout' href="/checkout" className="btn w-100">
                            Checkout
                        </Link>
                    </div>
                </>
            )}
            <ul></ul>
        </div>
    );
};

export default Cart;
