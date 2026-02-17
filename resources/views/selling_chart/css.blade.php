<style>
    .modal-dialog {
        box-shadow: none
    }

    .new_search textarea,
    .new_search textarea:focus {
        background: #374151;
        border: 1px solid #4f5154;
        color: #fff;
        min-height: 100px;
    }

    .table .new_select_field .form-control {
        padding: 8px 8px !important;
        font-size: 16px !important;
    }

    #selling_chart_table .new_table table tr td {
        text-align: center;
    }



    .bottom_cards {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        grid-gap: 20px;
        /* margin: 50px 0px 50px 0px; */
    }

    .bottom_item {
        padding: 20px;
        border-radius: 10px;
        display: flex;
        flex-direction: row;
        justify-content: flex-start;
        align-items: center;
        transition: .4s;
        margin-bottom: 0;
    }

    .bottom_item:hover {
        transform: translateX(4px);
    }

    .bottom_item h6 {
        color: #9CA3AF;
        font-size: 14px;
        font-weight: 500;
        margin-bottom: 5px;
    }

    .bottom_item p {
        font-size: 13px;
        margin-bottom: 0;
    }

    .bottom_icon {
        margin-right: 13px;
    }

    .bottom_icon i {
        background: #059669;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        font-size: 25px;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .filter_button.new_same_item {
        margin: 0.5rem !important;
    }

    .selling_chart_view_p p {
        text-transform: uppercase;
        font-size: 13px;
        margin-bottom: 4px;
    }

    .selling_chart_view_p h6 {
        text-transform: capitalize !important;
        font-size: 16px;
    }

    .selling_chart_view_p p span {
        margin-top: 7px;
        width: 95%;
    }

    .selling_chart_view_p p span img {
        height: 168px;
        width: 177px;
    }

    .toogle-item {
        display: none;
    }

    .form-check .form-check-input,
    .form-check label {
        cursor: pointer;
        font-size: 14px;
    }

    .table>:not(caption)>*>* {
        padding: 8px;
    }

    td,
    th {
        vertical-align: middle;
    }


    @media (max-width: 991px) {
        .bottom_cards {
            grid-template-columns: repeat(3, 1fr);
        }
    }

    @media (max-width: 767px) {
        .bottom_cards {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 575px) {
        .bottom_cards {
            grid-template-columns: repeat(1, 1fr);
        }

        form .card-dark {
            padding: 20px;
        }

        .top_title .tlt-btn,
        .filter_button button {
            height: 40px;
            font-size: 12px;
            padding: 0px 12px;
        }

        .selling_chart_view_p p span {
            margin-top: 7px;
            width: 95%;
        }

        .selling_chart_view_p p span img {
            height: 168px;
            width: 100%;
        }
    }

    .platform-divider {
        position: relative;
    }

    .divider-content {
        display: flex;
        align-items: center;
        gap: 30px;
        position: relative;
    }

    .divider-content .btn {
        padding: 0;
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .divider-line {
        flex: 1;
        height: 2px;
        position: relative;
    }

    /* 🌞 Light theme */
    html[data-bs-theme="light"] .divider-line {
        background: linear-gradient(90deg,
                transparent,
                rgba(0, 0, 0, 0.12),
                transparent);
    }

    /* 🌙 Dark theme */
    html[data-bs-theme="dark"] .divider-line {
        background: linear-gradient(90deg,
                transparent,
                rgba(255, 255, 255, 0.25),
                transparent);
    }
</style>
