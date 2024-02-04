import React, { Fragment } from 'react';
import Image from 'next/image';
import CommonMeta from '@/seo/MetaHead';
import { webPageSchema } from '@/seo/webPageSchema';
import { organizationSchema } from '@/seo/organizationSchema';
import { siteNavigationElement } from '@/seo/siteNavigationElement';
import { breadCrumbSchema } from '@/seo/breadCrumbSchema';
const title = 'Privacy Policy - AS INTERNATIONAL'
const description = 'AS INTERNATIONAL COMPANY PRIVACY AND POLICY'
const HOST = 'https://www.teflonbonehorncrafts.com/';
const url = 'https://www.teflonbonehorncrafts.com/privacyPolicy'
export default function () {
    return (
        <Fragment>
            <CommonMeta title="Privacy Policy - AS INTERNATIONAL" description="AS INTERNATIONAL COMPANY PRIVACY AND POLICY" />
            <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: webPageSchema(title, description, url) }} />
            <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: organizationSchema() }} />
            <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: siteNavigationElement() }} />
            <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: breadCrumbSchema(title, HOST, url) }} />
            <main className="policy_area section-padding pt--40">
                <div className="container-lg pt-5">
                    <div className="section-title" style={{ backgroundColor: '#f6f6f6', padding: '50px' }}>
                    <h1 className='d-none'>Privacy Policy -  AS INTERNATIONAL</h1>
                        <Image src="/assets/image/logo.webp" alt="Your Company Logo" width={150} height={100} />
                        <div className="row mt--30" style={{ textAlign: 'center' }}>
                            <h3 style={{ color: 'black' }}>Privacy Policy</h3>
                        </div>
                        <article className="policyArticle" style={{ textAlign: 'justify' }}>
                            <h5>Personal Information Collection</h5>
                            <p>We collect personal information when you voluntarily provide it to us. For example, when you register on our site, place an order or subscribe to our newsletter.</p>

                            <h5>Use of Your Information</h5>
                            <p>
                                The personal information we collect allows us to keep you posted on our latest product announcements, updates, and upcoming events. If you don't want to be on our
                                mailing list, you can opt-out anytime.
                            </p>

                            <h5>Cookies</h5>
                            <p>
                                We use cookies to help us remember and process the items in your shopping cart, understand and save your preferences for future visits, and compile aggregate data about
                                site traffic and site interactions.
                            </p>

                            <h5>Protection of Information</h5>
                            <p>
                                We implement a variety of security measures to maintain the safety of your personal information when you place an order or enter, submit, or access your personal
                                information.
                            </p>

                            <h5>Sharing of Information</h5>
                            <p>We do not sell, trade, or otherwise transfer to outside parties your personally identifiable information unless we provide you with advance notice.</p>

                            <h5>Consent</h5>
                            <p>By using our site, you consent to our privacy policy.</p>

                            <h5>Changes to the Privacy Policy</h5>
                            <p>
                                If we decide to change our privacy policy, we will post those changes on this page. Policy changes will apply only to information collected after the date of the
                                change.
                            </p>

                            <h5>Contacting Us</h5>
                            <p>If there are any questions regarding this privacy policy, you may contact us using the information below: [your_email@example.com]</p>
                        </article>
                    </div>
                </div>
            </main>
        </Fragment>
    );
}
