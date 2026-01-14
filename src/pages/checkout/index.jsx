import React, { useState } from 'react';
import Container from '@/components/Layout/Container';
import Checkout from '@/components/Layout/Checkout';
import countries from '@/constants/country';
import useSelector from '@/hooks/useSelector';
import { selectCartProduct } from '@/selector/cartSelector';
import BreadCrumb from '@/seo/BreadCrumb';
import { useRouter } from 'next/router';
import Paypal from '@/components/Paypal';
import { toast } from 'react-toastify';
import store from '@/lib/store/store';
import { CartHelper } from '@/lib/cart';


export default function ({PAYPAL_KEY}) {
    const title = 'Checkout - AS INTERNATIONAL PRODUCT CHECKOUT';
    const breadCrumbItems = [{ url: '/', name: 'Home' }, { name: 'Checkout' }];
    const router = useRouter();
    const cartProducts = useSelector(selectCartProduct) || [];
    const [checkbox, setCheckbox] = useState(false);
    const total = cartProducts
        .reduce((previousValue, currentValue) => {
            return previousValue + (currentValue.qty * currentValue.price);
        }, 0)
        .toFixed(2);
    const [placeOrder, setPlaceOrder] = useState(false);
    const [shippingData, setShippingData] = useState({
        name: '',
        companyName: '',
        email: '',
        city: '',
        state: '',
        zipCode: '',
        orderNote: '',
        addressLine1: '',
        addressLine2: '',
        mobile: '',
        country: '',
    });

    const handlePlaceOrder = (e) => {
        const key = Object.keys(shippingData);
        for (let i = 0; i < key.length; i++) {
            if (key[i] !== 'companyName' && key[i] !== 'addressLine2' && key[i] !== 'orderNote') {
                if (shippingData[key[i]] === '') {
                    toast.error(`Please enter your ${key[i]}`);
                    return;
                }
            }
        }

        if (cartProducts.length === 0) {
            toast.error('Please add products to the cart');
            return;
        }

        setPlaceOrder(true);
    };

    const handleOrderPlaced = (purchase_units, key) => {
        purchase_units[0].description.split(',').forEach((id) => {
            CartHelper.removeFromCart(id);
        });
        setShippingData({
            name: '',
            companyName: '',
            email: '',
            city: '',
            state: '',
            zipCode: '',
            orderNote: '',
            addressLine1: '',
            addressLine2: '',
            mobile: '',
            country: '',
        });
        setPlaceOrder(false);
        router.push(`/orderDetail/${key}`);
    };

    const handleOrderFailed = ({ payer, id, purchase_units, update_time }) => {
        setPlaceOrder(false);
        const purchasedProducts = purchase_units[0].description.split(',');
    };

    return (
        <>
            <BreadCrumb items={breadCrumbItems} text={title} />
            <div style={{ marginTop: '10px' }} />
            <Container>
                <Checkout>
                    <div className="col-12">
                        <h1 className="quick-title">CHECKOUT</h1>
                        <div className="checkout-quick-box">
                            <p>
                                <i className="far fa-sticky-note" />
                                Returning customer? <p className="slide-trigger">Click here</p>
                            </p>
                        </div>
                        <div className="checkout-slidedown-box" id="quick-login" style={{ display: 'none' }}>
                            <div className="quick-login-form">
                                <p>If you have shopped with us before, please enter your details in the boxes below. </p>
                                <div className="form-group">
                                    <label htmlFor="quick-user">Email *</label>
                                    <input type="text" onChange={(e) => setShippingData((s) => ({ ...s, email: e.target.value }))} required placeholder id="quick-user" />
                                </div>
                                <div className="form-group">
                                    <label htmlFor="quick-pass">Mobile No *</label>
                                    <input type="text" onChange={(e) => setShippingData((s) => ({ ...s, mobile: e.target.value }))} required placeholder id="quick-pass" />
                                </div>
                                <div className="form-group">
                                    <label htmlFor="quick-pass">Order Id *</label>
                                    <input type="text" onChange={(e) => setShippingData((s) => ({ ...s, orderId: e.target.value }))} required placeholder id="quick-pass" />
                                </div>
                                <div className="form-group">
                                    <div className="d-flex align-items-center">
                                        <button type="button" className="btn btn-black">
                                            Submit
                                        </button>
                                        <div className="d-inline-flex align-items-center  ms-3">
                                            <input type="checkbox" id="accept_terms" className="mb-0 me-1" />
                                            <label htmlFor="accept_terms" className="mb-0">
                                                I’ve read and accept the terms &amp; conditions
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className="col-lg-7 mb--20">
                        {/* Billing Address */}
                        <div id="billing-form" className="mb-40">
                            <h4 className="checkout-title">Shipping/Billing Address</h4>
                            <div className="row">
                                <div className="col-md-6 col-12 mb--20">
                                    <label>Full Name*</label>
                                    <input onChange={(e) => setShippingData((s) => ({ ...s, name: e.target.value }))} value={shippingData.name} type="text" required placeholder="Full Name" />
                                </div>

                                <div className="col-12 mb--20">
                                    <label>Company Name</label>
                                    <input
                                        onChange={(e) => setShippingData((s) => ({ ...s, companyName: e.target.value }))}
                                        value={shippingData.companyName}
                                        type="text"
                                        required
                                        placeholder="Company Name"
                                    />
                                </div>

                                <div className="col-md-6 col-12 mb--20">
                                    <label>Email Address*</label>
                                    <input onChange={(e) => setShippingData((s) => ({ ...s, email: e.target.value }))} value={shippingData.email} type="email" placeholder="Email Address" />
                                </div>
                                <div className="col-md-6 col-12 mb--20">
                                    <label>Phone no*</label>
                                    <input onChange={(e) => setShippingData((s) => ({ ...s, mobile: e.target.value }))} value={shippingData.mobile} type="text" required placeholder="Phone number" />
                                </div>
                                <div className="col-12 mb--20">
                                    <label>Address*</label>
                                    <input
                                        type="text"
                                        onChange={(e) => setShippingData((s) => ({ ...s, addressLine1: e.target.value }))}
                                        value={shippingData.addressLine1}
                                        required
                                        placeholder="Address line 1"
                                    />
                                    <input
                                        type="text"
                                        onChange={(e) => setShippingData((s) => ({ ...s, addressLine2: e.target.value }))}
                                        value={shippingData.addressLine2}
                                        required
                                        placeholder="Address line 2"
                                    />
                                </div>
                                <div className="col-12 col-12 mb--20">
                                    <label>Country*</label>
                                    <select onChange={(e) => setShippingData((s) => ({ ...s, country: e.target.value }))} value={shippingData.country} className="nice-select">
                                        {countries.map((country, i) => (
                                            <option key={i} value={country}>
                                                {country}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div className="col-md-6 col-12 mb--20">
                                    <label>Town/City*</label>
                                    <input onChange={(e) => setShippingData((s) => ({ ...s, city: e.target.value }))} value={shippingData.city} type="text" required placeholder="Town/City" />
                                </div>
                                <div className="col-md-6 col-12 mb--20">
                                    <label>State*</label>
                                    <input onChange={(e) => setShippingData((s) => ({ ...s, state: e.target.value }))} value={shippingData.state} type="text" required placeholder="State" />
                                </div>
                                <div className="col-md-6 col-12 mb--20">
                                    <label>Zip Code*</label>
                                    <input onChange={(e) => setShippingData((s) => ({ ...s, zipCode: e.target.value }))} value={shippingData.zipCode} type="text" required placeholder="Zip Code" />
                                </div>
                            </div>
                        </div>

                        <div className="order-note-block mt--30">
                            <label htmlFor="order-note">Order notes</label>
                            <textarea
                                onChange={(e) => setShippingData((s) => ({ ...s, orderNote: e.target.value }))}
                                value={shippingData.orderNote}
                                id="order-note"
                                cols={30}
                                rows={10}
                                className="order-note"
                                placeholder="Notes about your order, e.g. special notes for delivery."
                                defaultValue={''}
                            />
                        </div>
                    </div>
                    <div className="col-lg-5">
                        <div className="row">
                            {/* Cart Total */}
                            <div className="col-12">
                                <div className="checkout-cart-total">
                                    <h2 className="checkout-title">YOUR ORDER</h2>
                                    <h4>
                                        Product <span>Total</span>
                                    </h4>
                                    <ul>
                                        {cartProducts.map((product) => {
                                            <li>
                                                <span className="left">
                                                    {product.name} X {product.qty}
                                                </span>{' '}
                                                <span className="right">${product.qty * product.price}</span>
                                            </li>;
                                        })}
                                    </ul>
                                    <p>
                                        Sub Total <span>${total}</span>
                                    </p>
                                    <p className="text-primary">Shipping cost to be paid at the time of order confirmation</p>
                                    <h4>
                                        Grand Total <span>${total}</span>
                                    </h4>
                                    <div className="method-notice mt--25">
                                        <article>
                                            <h3 className="d-none sr-only">blog-article</h3>
                                            Sorry, it seems that there are no available payment methods for your state. Please contact us if you require assistance or wish to make alternate
                                            arrangements.
                                        </article>
                                    </div>
                                    <div className="term-block">
                                        <input
                                            type="checkbox"
                                            onChange={(e) => {
                                                setCheckbox(e.target.checked);
                                            }}
                                            id="accept_terms2"
                                        />
                                        <label htmlFor="accept_terms2" style={{ marginLeft: '5px' }}>
                                            I’ve read and accept the{' '}
                                            <span className="text-primary" onClick={() => router.push('/termsConditions')} style={{ cursor: 'pointer' }}>
                                                terms &amp; conditions
                                            </span>
                                        </label>
                                    </div>
                                    <Paypal
                                        amount={total}
                                        products={cartProducts}
                                        placeOrder={placeOrder}
                                        shippingDetail={shippingData}
                                        handleOrderPlaced={handleOrderPlaced}
                                        handleOrderFailed={handleOrderFailed}
                                        paypalKey={PAYPAL_KEY}
                                    >
                                        <button
                                            className="place-order w-100"
                                            style={{
                                                backgroundColor: checkbox ? '#24bbdb' : 'gray',
                                                color: 'white',
                                                display: `${placeOrder ? 'none' : 'block'}`,
                                            }}
                                            disabled={!checkbox}
                                            onClick={handlePlaceOrder}
                                        >
                                            Place order
                                        </button>
                                    </Paypal>
                                </div>
                            </div>
                        </div>
                    </div>
                </Checkout>
            </Container>
        </>
    );
}


export const getStaticProps = async (context) => {
    const paypalKey = process.env.PAYPAL || null;
    return {
        props: {
            PAYPAL_KEY: paypalKey,
        },
    };
};
