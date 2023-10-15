import React from 'react';

const OrderDetail = ({shippingDetail,products,OrderId,time}) => {
    return (
        <div className="order-details">
            <h1>Order Details</h1>
            <p>Ordered on 29 August 2023 | Order# 402-2129194-3949963</p>

            <div className="info-section">
                <div className="address">
                    <h3>Shipping Address</h3>
                    <p>deepshikha</p>
                    <p>H.no 1836 new ram nagar near uco bank Bhoop singh ki gali</p>
                    <p>Bhoop singh ki gali</p>
                    <p>ORAI, UTTAR PRADESH 285001</p>
                    <p>India</p>
                </div>

                <div className="order-summary">
                    <h3>Order Summary</h3>
                    <p>Item(s) Subtotal: ₹1,699.00</p>
                    <p>Shipping: ₹0.00</p>
                    <p>Total: ₹1,699.00</p>
                    <p>Grand Total: ₹1,699.00</p>
                </div>
            </div>

            <p>Delivered 31-Aug-2023</p>
            <p>Package was handed to resident</p>

            <div className="product-details">
                <img src="/path/to/image.png" alt="Apple EarPods" />
                <div>
                    <h3>Apple EarPods with Lightning Connector</h3>
                    <p>Sold by: Appario Retail Pvt Ltd</p>
                    <p>Return window closed on 07-Sept-2023</p>
                    <p>₹1,699.00</p>
                    <p>New</p>
                    <button>Buy it again</button>
                </div>
            </div>

            <div className="actions">
                <button>Get product support</button>
                <button>Leave seller feedback</button>
                <button>Write a product review</button>
                <button>Archive order</button>
            </div>
        </div>
    );
};

export default OrderDetail;
