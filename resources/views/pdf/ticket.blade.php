
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Transactions</title>
    <style>
        /* General Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }

        /* Outer container */
        .limiter {
            width: 100%;
            margin: 0 auto;
            padding: 50px 20px;
            background: #f5f7fa;
        }
        .section-title{
            padding: 20px;
            text-align: center;
        }
        /* Centering table container */
        .container-table100 {
            width: 100%;
            display: flex;
            justify-content: center;
        }

        /* Table wrapper */
        .wrap-table100 {
            width: 90%;
            background: #fff;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        /* Table */
        .table {
            width: 100%;
            display: table;
        }

        /* Table rows */
        .row {
            display: table-row;
            background: #fefefe;
        }

        .row.header {
            background: #2d8cf0;
            color:#014a98;
            font-weight: bold;
        }

        /* Table cells */
        .cell {
            display: table-cell;
            padding: 20px;
            text-align: left;
            vertical-align: middle;
            border-bottom: 1px solid #e0e0e0;
            position: relative;
        }

        /* Responsive label on small devices */
        .cell::before {
            content: attr(data-title);
            position: absolute;
            left: 20px;
            top: 20px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
            color: #999;
            display: none;
        }

        /* Row hover */
        .row:hover {
            background: #f1f7fd;
        }

        /* Responsive */
        @media screen and (max-width: 768px) {
            .table {
                display: block;
            }

            .row {
                display: block;
                padding: 10px 0;
            }

            .cell {
                display: block;
                padding-left: 50%;
                margin-bottom: 10px;
                border: none;
                border-bottom: 1px solid #eee;
            }

            .cell::before {
                display: block;
            }

            .row.header {
                display: none;
            }
        }
    </style>
</head>

<body>

    <div class="limiter">
        <div class="container-table100">
            <h2 class="section-title">Walikota Cup 2025 Ticket Transactions</h2>
            <div class="wrap-table100">
                <div class="table">

                    <div class="row header">
                        <div class="cell">Atas Nama</div>
                        <div class="cell">Kode Tiket</div>
                        <div class="cell">Jenis Tiket</div>
                    </div>
                    @foreach($tickets as $key => $ticket)
                            <div class="row">
                            <div class="cell">{{ $ticket->transaction->name }}</div>
                            <div class="cell">{{ $ticket->ticket_qr }}</div>
                            <div class="cell">{{ $ticket->transaction->matchDay->day }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

</body>

</html>
