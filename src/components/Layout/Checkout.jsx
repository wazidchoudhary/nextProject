import React from 'react';

const Checkout = ({ children }) => {
    return (
        <div className="row">
            <div className="col-12">
                <div className="checkout-form">
                    <div className="row row-40">
                        {children}
                    </div>
                </div>
            </div>
        </div>
    );
};

export default Checkout;
