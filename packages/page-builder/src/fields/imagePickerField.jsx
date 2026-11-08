import React, { useState } from 'react';

export function createImagePickerField({ uploadUrl }) {
    return {
        type: 'custom',
        render: ({ value, onChange }) => (
            <ImagePicker value={value} onChange={onChange} uploadUrl={uploadUrl} />
        ),
    };
}

function ImagePicker({ value, onChange, uploadUrl }) {
    const [uploading, setUploading] = useState(false);

    const upload = async (file) => {
        setUploading(true);
        const body = new FormData();
        body.append('image', file);
        const response = await fetch(uploadUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': readCsrfToken() },
            body,
        });
        setUploading(false);
        if (!response.ok) return;
        const json = await response.json();
        onChange({ media_id: json.id, url: json.url });
    };

    return (
        <div className="lc-page-builder-image-picker">
            {value?.url && <img src={value.url} alt="" className="lc-page-builder-image-picker__preview" />}
            <input
                type="file"
                accept="image/*"
                disabled={uploading}
                onChange={(event) => {
                    const file = event.target.files?.[0];
                    if (file) upload(file);
                }}
            />
            {value?.media_id && (
                <button type="button" onClick={() => onChange(null)}>Usuń</button>
            )}
        </div>
    );
}

function readCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}
