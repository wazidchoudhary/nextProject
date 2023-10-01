import React, { useEffect } from 'react';
import useSelector from '@/hooks/useSelector';
import { selectCartProduct } from '@/selector/cartSelector';
import store from '@/lib/store/store';
import { CartHelper } from '@/lib/cart';
import Image from 'next/image';
import Link from 'next/link';
import { EmptyProductSection } from './EmptyProductSection';
const Cart = () => {
    const cart = useSelector(selectCartProduct) || [];

    useEffect(() => {
        const fetchedFromLocalStorage = localStorage.getItem('asInternationalCart');
        store.dispatch({
            cart: fetchedFromLocalStorage ? JSON.parse(fetchedFromLocalStorage) : [],
        });
    }, []);

    return (
        <div className="sin-dropdown cart-dropdown">
            <div className="inner-single-block" style={{ maxHeight: '600px', overflowY: 'scroll' }}>
                {!cart.length && <EmptyProductSection height={100} width={100} description="Cart Is Empty" fontSize="20px" />}
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
            <div className="inner-single-block">
                <ul className="cart-details">
                    <li>
                        <span className="label">Subtotal</span> <span className="value">$500.00</span>
                    </li>
                    <li>
                        <span className="label">Shipping</span> <span className="value">€7.00</span>
                    </li>
                    <li>
                        <span className="label">Total</span> <span className="value">€507.00</span>
                    </li>
                </ul>
            </div>
            <div className="inner-single-block">
                <Link href="/checkout" className="btn w-100">
                    Checkout
                </Link>
            </div>
            <ul></ul>
        </div>
    );
};

export default Cart;
