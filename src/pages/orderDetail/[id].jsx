import React, { useEffect, useRef } from 'react';
import { FirebaseHelper } from '@/lib/firebase-helpers';
import datePipe from '@/utils/date-pipe';
import dynamic from 'next/dynamic';
import Image from 'next/image';
import { jsPDF } from 'jspdf';
import html2canvas from 'html2canvas';
// const JsPDF = dynamic(
//     () => import('jspdf'),
//     { ssr: false }
//   );
  
//   const html2canvas = dynamic(
//     () => import('html2canvas'),
//     { ssr: false }
//   );
export default function ({ orderDetail }) {
    console.log(orderDetail);
    const { time, orderId, products, shippingDetail } = orderDetail;
    const { name, addressLine1, addressLine2, city, country, email, mobile, state, zipCode, companyName } = shippingDetail;
    const total = products
        .reduce((previousValue, currentValue) => {
            return previousValue + currentValue.qty * currentValue.price;
        }, 0)
        .toFixed(2);

    const orderDetailHTML = useRef(null);

    const generatePDF = async () => {
        const element = orderDetailHTML.current;
        if (!element) return;

        try {
            console.log(element)
            
            const canvas = await html2canvas(element);
            const imgData = canvas.toDataURL('image/png');
          
            const pdf = new jsPDF('p', 'mm', 'a4');
            const imgProps = pdf.getImageProperties(imgData);
            const pdfWidth = pdf.internal.pageSize.getWidth();
            const pdfHeight = (imgProps.height * pdfWidth) / imgProps.width;
            pdf.addImage(imgData, 'JPEG', 0, 0, pdfWidth, pdfHeight);
            pdf.save(`${name}.pdf`);
        } catch (error) {
            console.error('Error generating PDF:', error);
        }
    };

    useEffect(() => {
        setTimeout(() => {
            generatePDF();
        }, 2000);
    }, []);

    return (
        <div className="order-details" ref={orderDetailHTML} style={{background:'white'}}>
            <h1>Order Details</h1>
            <h5>
                Ordered on {datePipe.dateConvert(time.split('T')[0])} | OrderId# {orderId}
            </h5>

            <div className="info-section">
                <div className="address">
                    <h4>Shipping Address</h4>
                    <br />
                    <h5>{name}</h5>
                    <p>Mobile : {mobile}</p>
                    <p>Email Id : {email}</p>
                    {companyName ? <p>Company : {companyName}</p> : null}
                    <p>Address : {addressLine1}, </p>
                    {addressLine2 ? <p style={{ marginLeft: '60px !important' }}>{addressLine2},</p> : null}
                    <p style={{ marginLeft: '60px !important' }}>
                        {city}, {state}, {zipCode},{country}
                    </p>
                </div>

                <div className="order-summary">
                    <h5>Order Summary</h5>
                    <p>Item(s) Subtotal: ${total}</p>
                    <p>Shipping: Amount Is Payable at the time of Order Confirmation</p>
                    <p style={{ color: 'black' }}>Grand Total: ${total}</p>
                </div>
            </div>

            <h5>Delivery will be done by Fedex</h5>
            <h5></h5>
            <h5>Shipping Id will be generated at the time of Order Confirmation</h5>

            {products.map((product, i) => {
                return (
                    <div key={i} className="product-details">
                        <Image width={150} height={150} src={product.image} alt={product.name} />
                        <div style={{ marginLeft: '15px' }}>
                            <h5>{product.name}</h5>
                            <p>Sold by: AS International</p>
                            <p>Price : ${product.price}</p>
                            <p>Quantity : {product.qty}</p>
                            <p>Color : {product.color}</p>
                            <p>Dimension : {product.dimension}</p>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

export const getServerSideProps = async (context) => {
    const orderId = context.query.id;
    const orderDetail = await FirebaseHelper.fetchOrderDetail(orderId);

    return {
        props: { orderDetail },
    };
};
