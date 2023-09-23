import React from 'react'

export default function () {
  return (
    <div>[...id]</div>
  )
}

export const getServerSideProps = (context) => {
  console.log(context.query, ' @@@context');

  return {
      props: {

      }
  }
}
