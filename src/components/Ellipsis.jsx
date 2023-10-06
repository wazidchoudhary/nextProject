import { useState } from 'react';

const Ellipsis = ({ text = '', className = '' }) => {
    const [isCollapsed, setIsCollapsed] = useState(true);

    const expand = () => setIsCollapsed(false);
    const collapse = () => setIsCollapsed(true);

    return (
        <p className={`${className} ${isCollapsed ? 'ellipsis' : ''}`} style={{ position: 'relative', fontSize: '17px' }}>
            {text}
            {isCollapsed && (
                <a onClick={expand} className="readMoreBtn">
                    ...Read More
                </a>
            )}

            {!isCollapsed && (
                <a onClick={collapse} className="text-prim">
                    ...Read Less
                </a>
            )}
        </p>
    );
};

export default Ellipsis;
