import React from 'react'

export default () => {
  return (
    <>
       <MetaHead title={'Knife Handles'} description={'Knife Handles Category Product'} />
            <section className="bg-image">
                <h2 className="sr-only">Site Breadcrumb</h2>
                <div className="container" style={{ height: "150px" }}></div>
            </section>
    </>
  )
}


export const getServerSideProps = async (context) => {
  const res = await FirebaseHelper.fetchProductByCategory('knifeHandles');

  return {
      props: {
          product: res,
      },
  };
};

