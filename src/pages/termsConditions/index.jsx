import React, { Fragment } from 'react';
import Image from 'next/image';
import CommonMeta from '@/seo/MetaHead';
export default function () {
    return (
        <Fragment>
            <CommonMeta title="Refund & Return Policy - AS INTERNATIONAL" description="AS INTERNATIONAL Refund and Return Policy" />
            <main className="policy_area section-padding pt--40">
                <div className="container-lg pt-5">
                    <div className="section-title" style={{ backgroundColor: '#f6f6f6', padding: '50px' }}>
                        <Image src="/assets/image/logo.png" alt="Your Company Logo" width={150} height={100} />
                        <div className="row mt--30" style={{ textAlign: 'center' }}>
                            <h3 style={{ color: 'black' }}>Return and Refund Policy</h3>
                        </div>
                        <article className="policyArticle" style={{ textAlign: 'justify' }}>
                            <h5>Duration of Policy</h5>
                            <p>
                                You have a span of 28 days post your purchase to request a return or refund from our end. If this period has elapsed since your purchase, unfortunately, a refund or an
                                exchange will not be feasible.
                            </p>

                            <h5>Return Eligibility Criteria</h5>
                            <p>
                                For an item to qualify for a return:
                               <br />
                                - The item should remain untouched, and in the pristine condition, you got it. <br />
                                - The original packaging should be intact. <br />
                               
                                However, specific goods are non-returnable. This includes perishable items such as edibles, plants, and newspapers. We strictly do not entertain returns for products
                                that fall under intimate wear, sanitary utilities, potentially dangerous materials, or any combustible liquid or gas.
                            </p>

                            <h5>Refund Procedure</h5>
                            <p>
                                Upon receiving and evaluating your returned product, you'll be notified via email about the status of your refund.
                                <br />
                                Once greenlit, the refund amount will reflect in your original payment method in a certain number of days.
                            </p>

                            <h5>Delay in Refunds</h5>
                            <p>
                                In case your refund hasn't shown up yet, we recommend: <br />
                                - Rechecking your bank statements. <br />
                                - Contacting your credit card company as processing might take time. <br />
                                - Reaching out to your bank for refund status.
                                <br />
                                If you've followed the above steps and still haven't received your refund, please get in touch with us at [your_email@example.com].
                            </p>

                            <h5>Sale Items</h5>
                            <p>Any item purchased during a sale is final and can't be refunded.</p>

                            <h5>Exchange Policy</h5>
                            <p>
                                Exchanges are limited to items that arrived in a faulty or damaged state. If you wish to replace such an item with a similar product, drop us an email at
                                [your_email@example.com] and post your product to: [Your Address Here].
                            </p>

                            <h5>Shipping Instructions for Returns</h5>
                            <p>
                                Products for return should be shipped to: [Your Address Here]. <br />
                                All shipping charges for returning the item will be borne by you. Furthermore, original shipping costs are non-refundable. In cases where a refund is sanctioned, the
                                return shipping charges will be subtracted from it. Considering the unpredictable shipping durations based on regions, the time frame for your exchanged product to
                                reach you can vary. For high-value returns, we advise opting for traceable shipping or even availing shipping insurance. We cannot affirmatively confirm the receipt of
                                your returned product.
                            </p>
                        </article>
                    </div>
                </div>
            </main>
        </Fragment>
    );
}
