import React from 'react';
import { FirebaseHelper } from '@/lib/firebase-helpers';

import { PayPalButtons, PayPalScriptProvider } from '@paypal/react-paypal-js';
import { toast } from 'react-toastify';

const Paypal = ({ amount, products, placeOrder, shippingDetail, children, handleOrderPlaced = () => {} ,handleOrderFailed = () =>{},paypalKey}) => {
   
    const options = {
        'client-id': paypalKey,
    };
   
    const createOrder = (data, action) => {
        return action.order
            .create({
                purchase_units: [
                    {
                        description: `${products.map((product) => product.id)}`,
                        amount: {
                            currency_code: 'USD',
                            value: amount,
                        },
                    },
                ],
                application_context: {
                    shipping_preference: 'NO_SHIPPING',
                },
            })
            .then((orderID) => {
                return orderID;
            });
    };
    const onApprove = (data, actions) => {
        return actions.order.capture().then(async (details) => {
            const { payer, id, purchase_units, update_time } = details;
            const Order = {
                orderId: id,
                products: products,
                shippingDetail: shippingDetail,
                time:update_time
            };
            const orderCompleted = await FirebaseHelper.createOrder(Order);
            
            if (orderCompleted.status) {
                handleOrderPlaced(purchase_units,orderCompleted.key);
            }else{
                handleOrderFailed({ payer, id, purchase_units, update_time })
            }
        });
    };
    const onError = (data, actions) => {
        console.log(data, actions);
        toast.error('Payment Failed');
    };
    const onCancel = () => {
        toast.error('Payment Canceled');
    };

    return (
        <PayPalScriptProvider options={options}>
            {children}
            {placeOrder ? <PayPalButtons style={{ layout: 'vertical' }} createOrder={createOrder} onApprove={onApprove} onError={onError} onCancel={onCancel} /> : ''}
        </PayPalScriptProvider>
    );
};

export default Paypal;
