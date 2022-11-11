
class Direction
{
    /**
     * @param {String} direction
     * @internal
     */
    constructor(
        direction,
    ) {
        this.direction = direction;
    }

    static tryFrom(direction)
    {
        switch (direction) {
            case 'asc':
                return new Direction('asc');
            case 'desc':
                return new Direction('desc');
            default:
                return null;
        }
    }

    static ascending()
    {
        return new Direction('asc');
    }

    static descending()
    {
        return new Direction('desc');
    }

    toFontawesomeIcon()
    {
        switch (this.direction) {
            case 'asc':
                return 'fa-sort-amount-down';
            case 'desc':
                return 'fa-sort-amount-up';
        }
    }

    toFactor()
    {
        switch (this.direction) {
            case 'asc':
                return 1;
            case 'desc':
                return -1;
        }
    }

    toOpposite()
    {
        switch (this.direction) {
            case 'asc':
                return new Direction('desc');
            case 'desc':
                return new Direction('asc');
        }
    }

    toString()
    {
        return this.direction;
    }
}


/**
 * @param {HTMLTableCellElement} header
 * @param {String} iconName
 */
const addIconToHeader = (header, iconName) => {
    header.innerHTML = header.innerHTML + ` <i class="fas ${iconName}"></i>`;
}

/**
 * @param {HTMLTableCellElement} header
 */
const tryRemoveIconFromHeader = (header) => {
    const icon = header.querySelector('i');
    if (icon) {
        header.removeChild(icon);
    }
}

/**
 * inspired by https://htmldom.dev/sort-a-table-by-clicking-its-headers/
 *
 * @param {Object} props
 * @param {HTMLTableElement} props.table
 * @param {HTMLTableCellElement} props.header
 * @param {Number} props.columnIndex
 */
const createSortColumnByIndex = ({table, header, columnIndex}) => () => {
    const tableBody = table.querySelector('tbody');
    const rows = tableBody.querySelectorAll('tr');

    table.querySelectorAll('[data-sort-column]').forEach((headerWithPossibleIcon) => {
        tryRemoveIconFromHeader(headerWithPossibleIcon);
    });

    const currentDirection = Direction.tryFrom(header.getAttribute('data-sort-column'))
    const direction = currentDirection ? currentDirection.toOpposite() : Direction.ascending();

    addIconToHeader(header, direction.toFontawesomeIcon());

    header.setAttribute('data-sort-column', direction.toString());

    // Clone the rows
    const newRows = Array.from(rows);

    // Sort rows by the content of cells
    newRows.sort((rowA, rowB) => {
        // Get the content of cells
        const cellA = rowA.querySelectorAll('td')[columnIndex].innerHTML;
        const cellB = rowB.querySelectorAll('td')[columnIndex].innerHTML;

        switch (true) {
            case cellA > cellB:
                return 1 * direction.toFactor();
            case cellA < cellB:
                return -1 * direction.toFactor();
            case cellA === cellB:
                return 0;
        }
    });

    for (const row of [...rows]) {
        tableBody.removeChild(row)
    }


    for (const newRow of [...newRows]) {
        tableBody.appendChild(newRow);
    }
}

document.querySelectorAll("[data-sort-table]").forEach((table) => {
    const headers = table.querySelectorAll('th');

    [...headers].forEach((header, columnIndex) => {
        if (header.getAttribute('data-sort-column') === null) {
            return;
        }
        header.addEventListener('click', createSortColumnByIndex({columnIndex, header, table}));
    })
})
