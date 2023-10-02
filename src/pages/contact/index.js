import React, { useState } from 'react';
import MetaHead from '@/seo/MetaHead';
import { FirebaseHelper } from '@/lib/firebase-helpers';
import { toast } from 'react-toastify';
export default function () {
    const [userData, setUserdata] = useState({
        name: '',
        email: '',
        subject: '',
        message: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        if (userData.name === '') {
            toast.error('Please enter your name');
            return;
        }
        if (userData.email === '') {
            toast.error('Please enter your Email');
            return;
        }
        FirebaseHelper.sendMessage(userData);
        setUserdata({
            name: '',
            email: '',
            subject: '',
            message: '',
        });
    };

    return (
        <>
            <MetaHead title="Contact Us - AS INTERNATIONAL" description="Contact As International If any enquiry" />
            <main className="contact_area section-padding pt--60">
                <div className="container">
                    <div className="row mt--60 space-db--30">
                        <div className="col-lg-5 col-md-5 col-12">
                            <div className="contact_adress">
                                <div className="ct_address">
                                    <h3 className="ct_title">Location &amp; Details</h3>
                                    <p>It is a long established fact that readewill be distracted by the readable content of a page when looking at ilayout.</p>
                                </div>
                                <div className="address_wrapper">
                                    <div className="address">
                                        <div className="icon">
                                            <i className="fas fa-map-marker-alt" />
                                        </div>
                                        <div className="contact-info-text">
                                            <p>
                                                <span>Address:</span> 1234 - Bandit Tringi lAliquam <br /> Vitae. New York
                                            </p>
                                        </div>
                                    </div>
                                    <div className="address">
                                        <div className="icon">
                                            <i className="far fa-envelope" />
                                        </div>
                                        <div className="contact-info-text">
                                            <p>
                                                <span>Email: </span> support@example.com{' '}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="address">
                                        <div className="icon">
                                            <i className="fas fa-mobile-alt" />
                                        </div>
                                        <div className="contact-info-text">
                                            <p>
                                                <span>Phone:</span> (800) 0123 456 789{' '}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div className="col-lg-7 col-md-7 col-12 mt-sm--30 mt-md--0">
                            <div className="contact_form">
                                <h3 className="ct_title">Send Us a Message</h3>
                                <form id="contact-form" action="https://whizthemes.com/mail-php/other/mail.php" className="contact-form">
                                    <div className="row">
                                        <div className="col-lg-12">
                                            <div className="form-group">
                                                <label>
                                                    Your Name <span className="required">*</span>
                                                </label>
                                                <input
                                                    type="text"
                                                    name="con_name"
                                                    id="con_name"
                                                    className="form-control"
                                                    value={userData.name}
                                                    onChange={(e) => setUserdata((s) => ({ ...s, name: e.target.value }))}
                                                    required
                                                />
                                            </div>
                                        </div>
                                        <div className="col-lg-12">
                                            <div className="form-group">
                                                <label>
                                                    Your Email <span className="required">*</span>
                                                </label>
                                                <input
                                                    type="email"
                                                    name="con_email"
                                                    id="con_email"
                                                    className="form-control"
                                                    value={userData.email}
                                                    onChange={(e) => setUserdata((s) => ({ ...s, email: e.target.value }))}
                                                    required
                                                />
                                            </div>
                                        </div>
                                        <div className="col-lg-12">
                                            <div className="form-group">
                                                <label>Subject</label>
                                                <input
                                                    type="text"
                                                    name="con_subject"
                                                    value={userData.subject}
                                                    onChange={(e) => setUserdata((s) => ({ ...s, subject: e.target.value }))}
                                                    className="form-control"
                                                    id="con_subject"
                                                />
                                            </div>
                                        </div>
                                        <div className="col-lg-12">
                                            <div className="form-group">
                                                <label>Your Message</label>
                                                <textarea
                                                    name="con_message"
                                                    value={userData.message}
                                                    onChange={(e) => setUserdata((s) => ({ ...s, message: e.target.value }))}
                                                    className="form-control"
                                                    id="con_message"
                                                    defaultValue={''}
                                                />
                                            </div>
                                        </div>
                                        <div className="col-lg-6">
                                            <div className="form-btn">
                                                <button type="submit" value="submit" id="submit" className="btn btn-black" onSubmit={handleSubmit} name="submit">
                                                    send
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                                <div className="form-output">
                                    <p className="form-message mt-2" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </>
    );
}
