/**
 * Sale Platform Module
 * Handles platform tree collapse/expand functionality
 * Uses window namespace for global access in inline onclick handlers
 */

/* ──────────────────────────────────────────────────
   Helper Functions (Private)
   ────────────────────────────────────────────────── */

function findDirectChildren(parentId) {
    const parentElement = document.querySelector(`[data-platform-id="${parentId}"]`);
    if (!parentElement) return [];

    const parentDepth = parseInt(parentElement.dataset.depth);
    const directChildDepth = parentDepth + 1;

    const allItems = document.querySelectorAll('.platform-item');
    const children = [];
    let foundParent = false;

    allItems.forEach(item => {
        if (item.dataset.platformId === parentId) {
            foundParent = true;
            return;
        }

        if (!foundParent) return;

        const itemDepth = parseInt(item.dataset.depth);

        // If we reach same or shallower depth than parent, stop
        if (itemDepth <= parentDepth) {
            foundParent = false;
            return;
        }

        // Add direct children only
        if (itemDepth === directChildDepth) {
            children.push(item);
        }
    });

    return children;
}

function collapseAllDescendants(platformId) {
    const allItems = document.querySelectorAll('.platform-item');
    const platformElement = document.querySelector(`[data-platform-id="${platformId}"]`);
    if (!platformElement) return;

    const platformDepth = parseInt(platformElement.dataset.depth);
    let foundPlatform = false;

    allItems.forEach(item => {
        if (item.dataset.platformId === platformId) {
            foundPlatform = true;
            return;
        }

        if (!foundPlatform) return;

        const itemDepth = parseInt(item.dataset.depth);

        // If we reach same or shallower depth, stop processing descendants
        if (itemDepth <= platformDepth) {
            foundPlatform = false;
            return;
        }

        // Hide this descendant
        item.classList.add('hidden');

        // Reset its toggle icon if it has one
        const icon = item.querySelector('.collapse-toggle-icon');
        if (icon) {
            icon.style.transform = 'rotate(-90deg)';
        }
    });
}

function closeSiblings(platformId) {
    const platform = document.querySelector(`[data-platform-id="${platformId}"]`);
    if (!platform) return;

    const platformDepth = parseInt(platform.dataset.depth);
    const allItems = document.querySelectorAll('.platform-item');

    allItems.forEach(item => {
        const itemDepth = parseInt(item.dataset.depth);

        // Only close siblings at same depth
        if (itemDepth !== platformDepth || item.dataset.platformId === platformId) {
            return;
        }

        // Check if this sibling has children visible
        const icon = item.querySelector('.collapse-toggle-icon');
        if (icon && icon.style.transform !== 'rotate(-90deg)') {
            // This sibling is open, close it
            collapseAllDescendants(item.dataset.platformId);
            icon.style.transform = 'rotate(-90deg)';
        }
    });
}

/* ──────────────────────────────────────────────────
   Public API (Exposed to window for inline onclick)
   ────────────────────────────────────────────────── */

/**
 * Platform Tree Collapse/Expand Functionality
 * - All items closed on first load
 * - Click parent to toggle children visibility
 * - Accordion behavior: opening one sibling closes others
 * - Recursive support for multi-level hierarchy
 */
window.togglePlatformCollapse = function(event, platformId) {
    // Prevent action buttons from triggering collapse
    if (event.target.closest('.flex.gap-1')) {
        return;
    }

    const platform = document.querySelector(`[data-platform-id="${platformId}"]`);
    if (!platform) return;

    const toggleIcon = platform.querySelector('.collapse-toggle-icon');
    const isCurrentlyHidden = toggleIcon && toggleIcon.style.transform === 'rotate(-90deg)';

    // Find all direct children
    const children = findDirectChildren(platformId);

    if (isCurrentlyHidden) {
        // Expand: show direct children
        children.forEach(child => {
            child.classList.remove('hidden');
        });
        // Rotate icon to open state
        if (toggleIcon) {
            toggleIcon.style.transform = 'rotate(0deg)';
        }
        // Close all sibling parents (accordion behavior)
        closeSiblings(platformId);
    } else {
        // Collapse: hide all descendants
        collapseAllDescendants(platformId);
        // Rotate icon to closed state
        if (toggleIcon) {
            toggleIcon.style.transform = 'rotate(-90deg)';
        }
    }
};

/* ──────────────────────────────────────────────────
   Initialization
   ────────────────────────────────────────────────── */

// Auto-slug generation on name blur
document.addEventListener('DOMContentLoaded', function() {
    const nameInput = document.querySelector('input[name="name"]');
    if (nameInput) {
        nameInput.addEventListener('blur', function() {
            const slugField = document.getElementById('slug');
            if (slugField && !slugField.value && this.value) {
                slugField.value = this.value.toLowerCase()
                    .replace(/\s+/g, '-')
                    .replace(/[^\w\-]/g, '');
            }
        });
    }

    // Initialize all items closed by default
    // Hide all children items (depth > 0)
    document.querySelectorAll('.platform-child').forEach(child => {
        child.classList.add('hidden');
    });

    // Set all toggle icons to closed state
    document.querySelectorAll('.collapse-toggle-icon').forEach(icon => {
        icon.style.transform = 'rotate(-90deg)';
    });
});
