import { FirebaseHelper } from '@/lib/firebase-helpers'
import { optionsDevelopment } from '@/lib/paypal/paypal.config'
import { PayPalButtons, PayPalScriptProvider } from '@paypal/react-paypal-js'
import React, { useState } from 'react'
import { toast } from 'react-toastify'

const Paypal = ({amount,products,placeOrder,shippingDetail,children}) => {
    const [orderId,setOrderId] = useState(null)
    const [paymentSuccess,setPaymentSuccess] = useState(null)
    const createOrder = (data,action) =>{
        return action.order.create({
            purchase_units:[
                {
                    description:'all products',
                    amount:{
                        currency_code:'USD',
                        value:amount
                    }
                }
            ],
            application_context:{
                shipping_preference:'NO_SHIPPING'
            }
        }).then(orderID => {
            return orderID
        })
    }
    const onApprove = (data,actions) =>{
        return actions.order.capture().then((details)=>{
            const { payer,id } = details
            setPaymentSuccess({
                payer:payer,
                paymentCompleted:true,
                orderId:id,
            })
            
            const Order = {
                orderId:id,
                products:products,
                shippingDetail:shippingDetail
            }
            FirebaseHelper.createOrder(Order)
        })
    }
    const onError = (data,actions) =>{
        console.log(data,actions)
        toast.error('Payment Failed')
    }
    const onCancel = () =>{
        toast.error('Payment Canceled')
    }

  return (
    <PayPalScriptProvider options={optionsDevelopment}>
      {children}
      {placeOrder ? <PayPalButtons style={{layout:'vertical'}} createOrder={createOrder} onApprove={onApprove} onError={onError} onCancel={onCancel} /> : '' }
      
    </PayPalScriptProvider>
  )
}

export default Paypal
