export default () => {
    return <>hello worlkld</>
}


export const getServerSideProps = (context) => {
    console.log(context.query, ' @@@context');

    return {
        props: {

        }
    }
}