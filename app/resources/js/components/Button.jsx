import React, { useState } from 'react';

export default function Button({  onClick=()=>{},className="rounded-xl mt-4 bg-gray-900 text-gray-50 font-semibold px-4 py-2 rounded hover:bg-gray-200",children,...props }) {

    return (
        <button onClick={onClick} className={className} {...props}>
            {children}
        </button>
    );
}