import { useState } from 'react';

const Ellipsis = ({ text = '', className = '' }) => {
    const [isCollapsed, setIsCollapsed] = useState(true);

    const expand = () => setIsCollapsed(false);

    return (
        <p className={`${className} ${isCollapsed ? 'ellipsis' : ''}`} style={{ position: 'relative', fontSize: '17px' }}>
            {text}
            {isCollapsed && (
                <a onClick={expand} className="readMoreBtn">
                    ...Read More
                </a>
            )}
        </p>
    );
};

export default Ellipsis;
