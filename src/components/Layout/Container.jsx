import React from 'react';

const Container = ({ children }) => {
    return (
        <section className="section-padding mt-5">
            <div className="container">{children}</div>
        </section>
    );
};

export default Container;
