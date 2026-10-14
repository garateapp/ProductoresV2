import React from 'react';

export default function GarcesPagination({ links }) {
  if (!links || links.length <= 3) return null;

  return (
    <div className="flex flex-wrap gap-1 mt-4">
      {links.map((link, i) => (
        <a
          key={i}
          href={link.url || '#'}
          className={`px-3 py-1 rounded border text-xs ${link.active ? 'bg-greenex-dark-green text-white' : 'bg-white'} ${!link.url ? 'opacity-50 pointer-events-none' : ''}`}
          dangerouslySetInnerHTML={{ __html: link.label }}
        />
      ))}
    </div>
  );
}
